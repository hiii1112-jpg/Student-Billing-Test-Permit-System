<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\ClassEnrollment;
use App\Models\ClassOffering;
use App\Models\ClearanceHold;
use App\Models\CoeCode;
use App\Models\ExamPeriod;
use App\Models\Program;
use App\Models\PromissoryNote;
use App\Models\SchoolTerm;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ClearanceService;
use App\Support\Audit;
use App\Support\Codes;
use App\Support\Notify;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrarController extends Controller
{
    public function dashboard()
    {
        return view('registrar.dashboard', [
            'term'        => SchoolTerm::current(),
            'maintenance' => Setting::get('maintenance') === '1',
            'counts'      => [
                'students'  => Student::where('status', 'active')->count(),
                'activated' => Student::where('is_activated', true)->count(),
                'teachers'  => User::where('role', 'teacher')->count(),
                'cashiers'  => User::where('role', 'cashier')->count(),
            ],
        ]);
    }

    /* ---------- Students ---------- */

    public function students(Request $request)
    {
        $students = Student::with('program')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('student_no', 'like', "%$s%")
                ->orWhere('last_name', 'like', "%$s%")
                ->orWhere('first_name', 'like', "%$s%")))
            ->orderBy('last_name')->paginate(20)->withQueryString();

        return view('registrar.students', [
            'students' => $students,
            'programs' => Program::orderBy('code')->get(),
            'blocks'   => Block::orderBy('year_level')->orderBy('name')->get(),
        ]);
    }

    private function createStudent(array $d): Student
    {
        return DB::transaction(function () use ($d) {
            $user = User::create([
                'username' => $d['student_no'],
                'name'     => $d['first_name'] . ' ' . $d['last_name'],
                'password' => Str::random(20),
                'role'     => 'student',
            ]);

            $student = Student::create([
                'user_id'    => $user->id,
                'student_no' => $d['student_no'],
                'first_name' => $d['first_name'],
                'last_name'  => $d['last_name'],
                'program_id' => $d['program_id'],
                'block_id'   => $d['block_id'] ?? null,
                'year_level' => $d['year_level'],
            ]);

            if ($term = SchoolTerm::current()) {
                CoeCode::create(['student_id' => $student->id, 'school_term_id' => $term->id, 'code' => Codes::coe()]);
            }

            return $student;
        });
    }

    public function studentStore(Request $request)
    {
        $d = $request->validate([
            'student_no' => 'required|string|max:20|unique:students,student_no|unique:users,username',
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'program_id' => 'required|exists:programs,id',
            'block_id'   => 'nullable|exists:blocks,id',
            'year_level' => 'required|integer|between:1,4',
        ]);

        $student = $this->createStudent($d);
        Audit::log('student.create', $student->student_no);

        $code = CoeCode::where('student_id', $student->id)->value('code');

        return redirect()->route('registrar.students.show', $student)
            ->with('status', 'Student created.' . ($code ? " COE activation code: $code" : ' Set the current semester first to generate a COE code.'));
    }

    public function import(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt']);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(ltrim($h, "\xEF\xBB\xBF"))), fgetcsv($handle) ?: []);
        $ok = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) === 1 && trim($row[0]) === '') {
                continue;
            }
            if (count($row) !== count($header)) {
                $errors[] = "Line $line: wrong number of columns.";
                continue;
            }

            $r = array_combine($header, array_map('trim', $row));
            $v = Validator::make($r, [
                'student_no'   => 'required|max:20|unique:students,student_no|unique:users,username',
                'first_name'   => 'required',
                'last_name'    => 'required',
                'program_code' => 'required|exists:programs,code',
                'year_level'   => 'required|integer|between:1,4',
            ]);

            if ($v->fails()) {
                $errors[] = "Line $line: " . implode(' ', $v->errors()->all());
                continue;
            }

            $program = Program::where('code', $r['program_code'])->first();
            $block = ! empty($r['block'] ?? '')
                ? Block::firstOrCreate(['program_id' => $program->id, 'year_level' => (int) $r['year_level'], 'name' => $r['block']])
                : null;

            $this->createStudent([
                'student_no' => $r['student_no'],
                'first_name' => $r['first_name'],
                'last_name'  => $r['last_name'],
                'program_id' => $program->id,
                'block_id'   => $block?->id,
                'year_level' => (int) $r['year_level'],
            ]);
            $ok++;
        }
        fclose($handle);

        Audit::log('student.import', "$ok imported, " . count($errors) . ' skipped');

        return back()->with('status', "$ok student(s) imported with COE codes.")->with('import_errors', $errors);
    }

    public function show(Student $student)
    {
        $term = SchoolTerm::current();
        $student->load('program', 'user', 'terms.schoolTerm');

        return view('registrar.student', [
            'student'   => $student,
            'coes'      => CoeCode::with('schoolTerm')->where('student_id', $student->id)->latest()->get(),
            'offerings' => $term ? ClassOffering::with('subject', 'block')->where('school_term_id', $term->id)->get() : collect(),
            'programs'  => Program::all(),
            'blocks'    => Block::orderBy('year_level')->orderBy('name')->get(),
            'holds'     => ClearanceHold::where('student_id', $student->id)->whereNull('released_at')->count(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $d = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'program_id' => 'required|exists:programs,id',
            'block_id'   => 'nullable|exists:blocks,id',
            'year_level' => 'required|integer|between:1,4',
        ]);

        $student->update($d);
        $student->user->update(['name' => $d['first_name'] . ' ' . $d['last_name']]);
        Audit::log('student.update', $student->student_no);

        return back()->with('status', 'Record updated.');
    }

    public function destroy(Student $student)
    {
        if ($student->terms()->whereHas('payments')->exists()) {
            return back()->withErrors(['delete' => 'Cannot delete: this student already has payment records.']);
        }

        Audit::log('student.delete', $student->student_no);
        $student->user->delete();

        return redirect()->route('registrar.students')->with('status', 'Student deleted.');
    }

    public function coe(Student $student)
    {
        $term = SchoolTerm::current();
        if (! $term) {
            return back()->withErrors(['coe' => 'Set the current semester first.']);
        }

        $existing = CoeCode::where('student_id', $student->id)->where('school_term_id', $term->id)->first();
        if ($existing && $existing->used_at) {
            return back()->withErrors(['coe' => 'The COE code for this semester was already used.']);
        }

        $coe = CoeCode::updateOrCreate(
            ['student_id' => $student->id, 'school_term_id' => $term->id],
            ['code' => Codes::coe(), 'used_at' => null]
        );
        Notify::to($student->user_id, 'A COE code was issued for you. Please get it from the Registrar.');
        Audit::log('coe.issue', $student->student_no);

        return back()->with('status', 'COE code: ' . $coe->code);
    }

    public function retake(Request $request, Student $student)
    {
        $d = $request->validate(['class_offering_id' => 'required|exists:class_offerings,id', 'fee' => 'required|numeric|min:0']);

        $offering = ClassOffering::findOrFail($d['class_offering_id']);
        $st = $student->terms()->where('school_term_id', $offering->school_term_id)->first();
        if (! $st) {
            return back()->withErrors(['fee' => 'The student has not unlocked this semester yet.']);
        }

        DB::transaction(function () use ($student, $offering, $st, $d) {
            ClassEnrollment::firstOrCreate(['class_offering_id' => $offering->id, 'student_id' => $student->id]);
            if ((float) $d['fee'] > 0) {
                app(BillingService::class)->addCharge($st, (float) $d['fee']);
            }
        });

        Notify::to($student->user_id, 'A retake subject was added and your billing was updated.');
        Audit::log('student.retake', $student->student_no . ' / ' . $offering->loading_code);

        return back()->with('status', 'Retake subject added and billing updated.');
    }

    public function exitStatus(Request $request, Student $student)
    {
        $d = $request->validate(['status' => 'required|in:graduated,transferred,dropped']);

        $student->load('terms');
        $problems = [];
        if ($student->terms->contains(fn ($t) => $t->balance() > 0)) {
            $problems[] = 'unpaid balance';
        }
        if (PromissoryNote::whereIn('student_term_id', $student->terms->pluck('id'))->where('status', 'active')->exists()) {
            $problems[] = 'active promissory note';
        }
        if (ClearanceHold::where('student_id', $student->id)->whereNull('released_at')->exists()) {
            $problems[] = 'cashier hold';
        }
        if ($problems) {
            return back()->withErrors(['status' => 'Cashier clearance failed: ' . implode(', ', $problems) . '.']);
        }

        $student->update(['status' => $d['status']]);
        $student->user->update(['is_active' => false]);
        Audit::log('student.exit', $student->student_no . ' => ' . $d['status']);

        return back()->with('status', 'Exit status updated and account disabled.');
    }

    public function clearancePrint(Student $student)
    {
        $clear = app(ClearanceService::class);
        $terms = $student->terms()->with('schoolTerm', 'billingPeriods')->get()->map(function ($t) use ($clear) {
            $t->statuses = collect(range(1, 4))->mapWithKeys(fn ($n) => [$n => $clear->statusFor($t, $n)]);
            return $t;
        });

        return view('registrar.print', [
            'student' => $student->load('program'),
            'terms'   => $terms,
            'holds'   => ClearanceHold::where('student_id', $student->id)->whereNull('released_at')->count(),
        ]);
    }

    /* ---------- Staff / accounts ---------- */

    public function staff()
    {
        return view('registrar.staff', [
            'users' => User::whereIn('role', ['teacher', 'cashier'])->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function staffStore(Request $request)
    {
        $d = $request->validate([
            'username'     => 'required|string|max:50|unique:users,username',
            'name'         => 'required|string|max:100',
            'role'         => 'required|in:teacher,cashier',
            'cashier_type' => 'required_if:role,cashier|nullable|in:head,assistant',
            'password'     => 'required|min:8',
        ]);

        User::create([
            'username'     => $d['username'],
            'name'         => $d['name'],
            'role'         => $d['role'],
            'cashier_type' => $d['role'] === 'cashier' ? $d['cashier_type'] : null,
            'password'     => $d['password'],
        ]);
        Audit::log('staff.create', $d['username'] . ' (' . $d['role'] . ')');

        return back()->with('status', 'Account created.');
    }

    public function userToggle(User $user)
    {
        abort_if($user->id == auth()->id(), 403);
        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('user.toggle', $user->username . ' => ' . ($user->is_active ? 'active' : 'disabled'));

        return back()->with('status', 'Account status changed.');
    }

    public function userReset(User $user)
    {
        $new = Str::random(10);
        $user->update(['password' => $new]);
        Audit::log('user.reset_password', $user->username);

        return back()->with('status', "New password for {$user->username}: $new");
    }

    /* ---------- Programs / blocks / subjects ---------- */

    public function setup()
    {
        return view('registrar.setup', [
            'programs' => Program::orderBy('code')->get(),
            'blocks'   => Block::with('program')->orderBy('program_id')->orderBy('year_level')->orderBy('name')->get(),
            'subjects' => Subject::orderBy('code')->get(),
        ]);
    }

    public function setupStore(Request $request)
    {
        switch ($request->input('type')) {
            case 'program':
                Program::create($request->validate(['code' => 'required|max:20|unique:programs,code', 'name' => 'required|max:255']));
                break;
            case 'block':
                Block::create($request->validate([
                    'program_id' => 'required|exists:programs,id',
                    'year_level' => 'required|integer|between:1,4',
                    'name'       => 'required|max:50',
                ]));
                break;
            case 'subject':
                Subject::create($request->validate(['code' => 'required|max:20|unique:subjects,code', 'title' => 'required|max:255', 'units' => 'required|integer|between:1,9']));
                break;
            default:
                abort(422);
        }

        return back()->with('status', 'Saved.');
    }

    /* ---------- Teacher load ---------- */

    public function loads()
    {
        $term = SchoolTerm::current();

        return view('registrar.loads', [
            'term'      => $term,
            'offerings' => $term ? ClassOffering::with('subject', 'block', 'teacher')->withCount('enrollments')->where('school_term_id', $term->id)->get() : collect(),
            'subjects'  => Subject::orderBy('code')->get(),
            'blocks'    => Block::orderBy('year_level')->orderBy('name')->get(),
            'teachers'  => User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function loadStore(Request $request)
    {
        $d = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'block_id'   => 'required|exists:blocks,id',
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', 'teacher')],
            'schedule'   => 'nullable|string|max:120',
        ]);

        $term = SchoolTerm::current();
        if (! $term) {
            return back()->withErrors(['subject_id' => 'Set the current semester first.']);
        }
        if (ClassOffering::where('school_term_id', $term->id)->where('subject_id', $d['subject_id'])->where('block_id', $d['block_id'])->exists()) {
            return back()->withErrors(['subject_id' => 'This subject is already loaded for that block this semester.']);
        }

        $offering = DB::transaction(function () use ($d, $term) {
            $o = ClassOffering::create($d + ['school_term_id' => $term->id, 'loading_code' => Codes::loading()]);

            Student::where('block_id', $d['block_id'])->where('status', 'active')->pluck('id')
                ->each(fn ($id) => ClassEnrollment::firstOrCreate(['class_offering_id' => $o->id, 'student_id' => $id]));

            return $o;
        });

        Notify::to((int) $d['teacher_id'], 'New subject load assigned. Loading code: ' . $offering->loading_code);
        Audit::log('load.create', $offering->loading_code);

        return back()->with('status', 'Load created. Loading code: ' . $offering->loading_code);
    }

    /* ---------- Semester switch / block progression ---------- */

    public function terms()
    {
        return view('registrar.terms', ['terms' => SchoolTerm::orderByDesc('id')->get()]);
    }

    public function termStore(Request $request)
    {
        $d = $request->validate([
            'school_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester'    => 'required|in:1,2',
        ]);

        if (SchoolTerm::where('school_year', $d['school_year'])->where('semester', $d['semester'])->exists()) {
            return back()->withErrors(['school_year' => 'That school year and semester already exists.']);
        }

        DB::transaction(function () use ($d) {
            $prev = SchoolTerm::current();

            SchoolTerm::query()->update(['is_current' => false]);
            $term = SchoolTerm::create($d + ['is_current' => true]);

            foreach ([1 => 'Prelim', 2 => 'Midterm', 3 => 'Pre-Final', 4 => 'Final'] as $no => $name) {
                ExamPeriod::create(['school_term_id' => $term->id, 'period_no' => $no, 'name' => $name]);
            }

            if ($prev && $prev->school_year !== $term->school_year) {
                Student::with('block')->where('status', 'active')->where('year_level', '<', 4)->get()->each(function ($s) {
                    $next = $s->year_level + 1;
                    $block = $s->block
                        ? Block::where('program_id', $s->program_id)->where('year_level', $next)->where('name', $s->block->name)->first()
                        : null;
                    $s->update(['year_level' => $next, 'block_id' => $block?->id]);
                });
            }

            Student::where('status', 'active')->where('is_activated', true)->get()->each(function ($s) use ($term) {
                CoeCode::create(['student_id' => $s->id, 'school_term_id' => $term->id, 'code' => Codes::coe()]);
                Notify::to($s->user_id, "New semester ({$term->school_year} Sem {$term->semester}): get your COE code from the Registrar to unlock it.");
            });
        });

        Audit::log('term.switch', $d['school_year'] . ' Sem ' . $d['semester']);

        return back()->with('status', 'Semester switched. COE codes were generated for activated students.');
    }

    /* ---------- Logs / maintenance ---------- */

    public function logs()
    {
        $logs = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.*', 'users.username')
            ->orderByDesc('activity_logs.id')->paginate(30);

        return view('registrar.logs', ['logs' => $logs]);
    }

    public function maintenance()
    {
        Setting::set('maintenance', Setting::get('maintenance') === '1' ? '0' : '1');
        Audit::log('maintenance.toggle', Setting::get('maintenance'));

        return back()->with('status', 'Maintenance mode updated.');
    }
}