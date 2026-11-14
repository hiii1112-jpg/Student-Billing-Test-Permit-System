<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassOffering;
use App\Models\ExamPeriod;
use App\Models\ExamRecord;
use App\Models\SchoolTerm;
use App\Models\Student;
use App\Models\StudentTerm;
use App\Services\ClearanceService;
use App\Support\Audit;
use App\Support\Notify;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function home(Request $request)
    {
        $me = $request->user();

        $offerings = ClassOffering::with('subject', 'block', 'schoolTerm')->withCount('enrollments')
            ->where('teacher_id', $me->id)->whereNotNull('claimed_at')
            ->when($request->term, fn ($q, $v) => $q->where('school_term_id', $v))
            ->when($request->year_level, fn ($q, $v) => $q->whereHas('block', fn ($b) => $b->where('year_level', $v)))
            ->get();

        return view('teacher.home', [
            'offerings' => $offerings,
            'terms'     => SchoolTerm::orderByDesc('id')->get(),
            'current'   => SchoolTerm::current(),
        ]);
    }

    public function claim(Request $request)
    {
        $request->validate(['loading_code' => 'required|string']);

        $offering = ClassOffering::where('teacher_id', $request->user()->id)
            ->where('loading_code', strtoupper(trim($request->loading_code)))->first();

        if (! $offering) {
            return back()->withErrors(['loading_code' => 'Invalid loading code.']);
        }
        if (! $offering->claimed_at) {
            $offering->update(['claimed_at' => now()]);
        }
        Audit::log('load.claim', $offering->loading_code);

        return back()->with('status', 'Subject load added to your classes.');
    }

    public function showClass(Request $request, ClassOffering $offering)
    {
        $this->mine($offering);
        $offering->load('subject', 'block', 'schoolTerm');

        $periods = ExamPeriod::where('school_term_id', $offering->school_term_id)->orderBy('period_no')->get();
        $period = $periods->firstWhere('period_no', (int) ($request->period ?: 1)) ?? $periods->first();
        abort_unless($period, 404);

        $clear = app(ClearanceService::class);

        $rows = ClassEnrollment::with('student')->where('class_offering_id', $offering->id)->get()
            ->map(function ($e) use ($offering, $period, $clear) {
                $st = StudentTerm::where('student_id', $e->student_id)->where('school_term_id', $offering->school_term_id)->first();

                return (object) [
                    'enrollment' => $e,
                    'student'    => $e->student,
                    'status'     => $st ? $clear->statusFor($st, $period->period_no) : ClearanceService::RED,
                    'record'     => ExamRecord::with('markedBy')->where('class_enrollment_id', $e->id)->where('exam_period_id', $period->id)->first(),
                ];
            })
            ->sortBy(fn ($r) => $r->student->last_name)->values();

        return view('teacher.class', [
            'offering' => $offering,
            'periods'  => $periods,
            'period'   => $period,
            'rows'     => $rows,
            'editable' => $offering->school_term_id == optional(SchoolTerm::current())->id,
        ]);
    }

    public function addStudent(Request $request, ClassOffering $offering)
    {
        $this->mine($offering, true);
        $request->validate(['student_no' => 'required|string']);

        $student = Student::where('student_no', trim($request->student_no))->where('status', 'active')->first();
        if (! $student) {
            return back()->withErrors(['student_no' => 'Student ID not found or not active.']);
        }
        if ($student->program_id != $offering->block->program_id) {
            return back()->withErrors(['student_no' => 'That student belongs to a different program.']);
        }

        ClassEnrollment::firstOrCreate(['class_offering_id' => $offering->id, 'student_id' => $student->id]);
        Audit::log('roster.add', $student->student_no . ' / ' . $offering->loading_code);

        return back()->with('status', 'Student added to the roster.');
    }

    public function removeStudent(ClassOffering $offering, ClassEnrollment $enrollment)
    {
        $this->mine($offering, true);
        abort_unless($enrollment->class_offering_id == $offering->id, 404);

        if (ExamRecord::where('class_enrollment_id', $enrollment->id)->whereIn('status', ['completed', 'special_completed'])->exists()) {
            return back()->withErrors(['remove' => 'Cannot remove a student who already has completed exams.']);
        }

        $enrollment->delete();
        Audit::log('roster.remove', 'student #' . $enrollment->student_id . ' / ' . $offering->loading_code);

        return back()->with('status', 'Student removed from the roster.');
    }

    public function markAbsent(Request $request, ClassOffering $offering, ClassEnrollment $enrollment)
    {
        $this->mine($offering, true);
        abort_unless($enrollment->class_offering_id == $offering->id, 404);
        $period = ExamPeriod::where('school_term_id', $offering->school_term_id)->findOrFail($request->exam_period_id);

        $rec = ExamRecord::firstOrNew(['class_enrollment_id' => $enrollment->id, 'exam_period_id' => $period->id]);
        if (in_array($rec->status, ['completed', 'special_completed'], true)) {
            return back()->withErrors(['absent' => 'Already completed.']);
        }

        $rec->status = $rec->status === 'absent' ? 'pending' : 'absent';
        $rec->marked_by = $request->user()->id;
        $rec->marked_at = now();
        $rec->save();

        return back();
    }

    public function bulkOk(Request $request, ClassOffering $offering)
    {
        $this->mine($offering, true);
        $period = ExamPeriod::where('school_term_id', $offering->school_term_id)->findOrFail($request->exam_period_id);

        $done = $excluded = $skipped = 0;

        foreach (ClassEnrollment::with('student')->where('class_offering_id', $offering->id)->get() as $e) {
            $rec = ExamRecord::firstOrNew(['class_enrollment_id' => $e->id, 'exam_period_id' => $period->id]);

            if (in_array($rec->status, ['completed', 'special_completed', 'absent'], true)) {
                $skipped++;
                continue;
            }

            $status = $this->clearanceOf($e, $offering, $period->period_no);

            if ($status === ClearanceService::RED) {
                $rec->fill(['status' => 'missed', 'clearance_status' => 'red']);
                $excluded++;
                Notify::to($e->student->user_id, "You were marked MISSED for the {$period->name} exam in {$offering->subject->code}: unpaid balance. Settle at the cashier, then ask your teacher for the special exam.");
            } else {
                $rec->fill(['status' => 'completed', 'clearance_status' => $status]);
                $done++;
                Notify::to($e->student->user_id, "Your {$period->name} exam in {$offering->subject->code} was marked COMPLETED.");
            }

            $rec->marked_by = $request->user()->id;
            $rec->marked_at = now();
            $rec->save();
        }

        Audit::log('exam.bulk_ok', $offering->loading_code . ' / ' . $period->name);

        return back()->with('status', "Bulk OK done: $done completed, $excluded excluded (RED), $skipped skipped (absent/already marked).");
    }

    public function markSpecial(Request $request, ClassOffering $offering, ClassEnrollment $enrollment)
    {
        $this->mine($offering, true);
        abort_unless($enrollment->class_offering_id == $offering->id, 404);
        $period = ExamPeriod::where('school_term_id', $offering->school_term_id)->findOrFail($request->exam_period_id);

        $rec = ExamRecord::firstOrNew(['class_enrollment_id' => $enrollment->id, 'exam_period_id' => $period->id]);
        if (in_array($rec->status, ['completed', 'special_completed'], true)) {
            return back()->withErrors(['special' => 'Already completed.']);
        }

        $status = $this->clearanceOf($enrollment, $offering, $period->period_no);
        if ($status === ClearanceService::RED) {
            return back()->withErrors(['special' => 'Student is still UNPAID (RED). Wait for the cashier payment or promissory.']);
        }

        $rec->fill([
            'status'           => 'special_completed',
            'clearance_status' => $status,
            'marked_by'        => $request->user()->id,
            'marked_at'        => now(),
        ])->save();

        Notify::to($enrollment->student->user_id, "Your special {$period->name} exam in {$offering->subject->code} was marked COMPLETED.");
        Audit::log('exam.special', $offering->loading_code . ' / student #' . $enrollment->student_id);

        return back()->with('status', 'Special exam marked as completed.');
    }

    private function mine(ClassOffering $offering, bool $editable = false): void
    {
        abort_unless($offering->teacher_id == auth()->id() && $offering->claimed_at, 403);

        if ($editable) {
            abort_unless($offering->school_term_id == optional(SchoolTerm::current())->id, 403);
        }
    }

    private function clearanceOf(ClassEnrollment $e, ClassOffering $o, int $periodNo): string
    {
        $st = StudentTerm::where('student_id', $e->student_id)->where('school_term_id', $o->school_term_id)->first();

        return $st ? app(ClearanceService::class)->statusFor($st, $periodNo) : ClearanceService::RED;
    }
}