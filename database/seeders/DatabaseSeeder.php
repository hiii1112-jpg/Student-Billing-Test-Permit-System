<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\ClassEnrollment;
use App\Models\ClassOffering;
use App\Models\CoeCode;
use App\Models\ExamPeriod;
use App\Models\Program;
use App\Models\SchoolTerm;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $term = SchoolTerm::create(['school_year' => '2026-2027', 'semester' => 1, 'is_current' => true]);
        foreach ([1 => 'Prelim', 2 => 'Midterm', 3 => 'Pre-Final', 4 => 'Final'] as $no => $name) {
            ExamPeriod::create(['school_term_id' => $term->id, 'period_no' => $no, 'name' => $name]);
        }

        User::create(['username' => 'registrar', 'name' => 'Registrar', 'password' => 'password', 'role' => 'registrar']);
        User::create(['username' => 'cashier1', 'name' => 'Head Cashier', 'password' => 'password', 'role' => 'cashier', 'cashier_type' => 'head']);
        User::create(['username' => 'cashier2', 'name' => 'Student Assistant', 'password' => 'password', 'role' => 'cashier', 'cashier_type' => 'assistant']);
        $teacher = User::create(['username' => 'teacher1', 'name' => 'Sample Teacher', 'password' => 'password', 'role' => 'teacher']);

        $program = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $block = Block::create(['program_id' => $program->id, 'year_level' => 1, 'name' => 'A']);
        $subject = Subject::create(['code' => 'IT101', 'title' => 'Intro to Computing', 'units' => 3]);

        // Student 1: already activated, term unlocked
        $u1 = User::create(['username' => '2026-0001', 'name' => 'Juan Dela Cruz', 'password' => 'password', 'role' => 'student']);
        $s1 = Student::create([
            'user_id' => $u1->id, 'student_no' => '2026-0001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'program_id' => $program->id, 'block_id' => $block->id, 'year_level' => 1, 'is_activated' => true,
        ]);
        app(BillingService::class)->openTerm($s1, $term, 1);

        // Student 2: not yet activated (test the COE code)
        $u2 = User::create(['username' => '2026-0002', 'name' => 'Maria Santos', 'password' => Str::random(20), 'role' => 'student']);
        $s2 = Student::create([
            'user_id' => $u2->id, 'student_no' => '2026-0002', 'first_name' => 'Maria', 'last_name' => 'Santos',
            'program_id' => $program->id, 'block_id' => $block->id, 'year_level' => 1,
        ]);
        CoeCode::create(['student_id' => $s2->id, 'school_term_id' => $term->id, 'code' => 'COE-DEMO0001']);

        // Teacher load (teacher must claim it with the loading code)
        $offering = ClassOffering::create([
            'school_term_id' => $term->id, 'subject_id' => $subject->id, 'block_id' => $block->id,
            'teacher_id' => $teacher->id, 'loading_code' => 'LD-TEST01', 'schedule' => 'MWF 8:00-9:00',
        ]);
        foreach ([$s1, $s2] as $s) {
            ClassEnrollment::create(['class_offering_id' => $offering->id, 'student_id' => $s->id]);
        }
    }
}