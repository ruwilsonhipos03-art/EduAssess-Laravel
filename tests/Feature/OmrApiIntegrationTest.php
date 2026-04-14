<?php

namespace Tests\Feature;

use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OmrApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('omr.api.base_url', 'http://python-omr.test');
        Config::set('omr.api.timeout_seconds', 30);
        Config::set('omr.api.bearer_token', 'test-omr-token');
        Config::set('omr.api.paths.entrance', '/api/omr/check-exam');
        Config::set('omr.api.paths.term', '/api/omr/check-term');
    }

    public function test_entrance_omr_check_processes_successfully_via_python_api(): void
    {
        $examiner = $this->createUser('entrance_examiner');
        $employee = $this->createEmployee($examiner);
        $studentUser = $this->createUser('student');

        $exam = Exam::query()->create([
            'Exam_Title' => 'Mock Exam',
            'Exam_Type' => 'midterm',
            'created_by' => $employee->id,
        ]);

        $subject = Subject::query()->create(['Subject_Name' => 'Math']);
        $examSubject = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'Starting_Number' => 1,
            'Ending_Number' => 5,
            'user_id' => $examiner->id,
        ]);

        AnswerKey::query()->create([
            'user_id' => $examiner->id,
            'exam_id' => $exam->id,
            'exam_subject_id' => $examSubject->id,
            'answers' => ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'A'],
        ]);

        $sheet = AnswerSheet::query()->create([
            'qr_payload' => 'QR-ENTRANCE-001',
            'exam_id' => $exam->id,
            'user_id' => $studentUser->id,
            'status' => 'generated',
        ]);

        Http::fake([
            'http://python-omr.test/api/omr/check-exam' => Http::response([
                'sheet_id' => 'QR-ENTRANCE-001',
                'answers' => ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'A'],
            ], 200),
        ]);

        Sanctum::actingAs($examiner);

        $response = $this->post('/api/entrance/omr/check', [
            'image' => UploadedFile::fake()->image('entrance.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('processed.0.success', true);

        $this->assertDatabaseHas('answer_sheets', [
            'id' => $sheet->id,
            'status' => 'checked',
            'total_score' => 5,
        ]);

        $this->assertDatabaseHas('exam_results', [
            'answer_sheet_id' => $sheet->id,
            'subject_id' => $subject->id,
            'raw_score' => 5,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://python-omr.test/api/omr/check-exam'
                && $request->hasHeader('Authorization', 'Bearer test-omr-token');
        });
    }

    public function test_term_omr_check_processes_successfully_via_python_api(): void
    {
        $instructor = $this->createUser('instructor');
        $employee = $this->createEmployee($instructor);
        $studentUser = $this->createUser('student');

        Student::query()->create([
            'user_id' => $studentUser->id,
            'Student_Number' => '2026-01-20001',
        ]);

        $exam = Exam::query()->create([
            'Exam_Title' => 'Term Exam',
            'Exam_Type' => 'term',
            'created_by' => $employee->id,
        ]);

        $subject = Subject::query()->create(['Subject_Name' => 'English']);
        $examSubject = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'Starting_Number' => 1,
            'Ending_Number' => 3,
            'user_id' => $instructor->id,
        ]);

        AnswerKey::query()->create([
            'user_id' => $instructor->id,
            'exam_id' => $exam->id,
            'exam_subject_id' => $examSubject->id,
            'answers' => ['1' => 'A', '2' => 'B', '3' => 'C'],
        ]);

        $sheet = AnswerSheet::query()->create([
            'qr_payload' => 'TERM-QR-001',
            'exam_id' => $exam->id,
            'user_id' => $studentUser->id,
            'status' => 'generated',
        ]);

        Http::fake([
            'http://python-omr.test/api/omr/check-term' => Http::response([
                'sheet_id' => '2026-01-20001|' . $exam->id . '|' . $subject->id . '|Doe|John|A|',
                'answers' => ['1' => 'A', '2' => 'B', '3' => 'C'],
            ], 200),
        ]);

        Sanctum::actingAs($instructor);

        $response = $this->post('/api/instructor/omr/check-term', [
            'image' => UploadedFile::fake()->image('term.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('processed.0.success', true);

        $this->assertDatabaseHas('answer_sheets', [
            'id' => $sheet->id,
            'status' => 'checked',
            'total_score' => 3,
        ]);

        $this->assertDatabaseHas('exam_results', [
            'answer_sheet_id' => $sheet->id,
            'subject_id' => $subject->id,
            'raw_score' => 3,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://python-omr.test/api/omr/check-term'
                && $request->hasHeader('Authorization', 'Bearer test-omr-token');
        });
    }

    public function test_entrance_omr_returns_python_error_message(): void
    {
        $examiner = $this->createUser('entrance_examiner');

        Http::fake([
            'http://python-omr.test/api/omr/check-exam' => Http::response([
                'error' => 'Image quality too low',
            ], 422),
        ]);

        Sanctum::actingAs($examiner);

        $response = $this->post('/api/entrance/omr/check', [
            'image' => UploadedFile::fake()->image('error.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('processed.0.success', false)
            ->assertJsonPath('processed.0.message', 'Image quality too low');
    }

    public function test_entrance_omr_handles_malformed_python_json_response(): void
    {
        $examiner = $this->createUser('entrance_examiner');

        Http::fake([
            'http://python-omr.test/api/omr/check-exam' => Http::response(
                'not-json',
                200,
                ['Content-Type' => 'text/plain']
            ),
        ]);

        Sanctum::actingAs($examiner);

        $response = $this->post('/api/entrance/omr/check', [
            'image' => UploadedFile::fake()->image('bad-json.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('processed.0.success', false)
            ->assertJsonPath('processed.0.message', 'Invalid JSON response from OMR API.');
    }

    private function createUser(string $role): User
    {
        static $counter = 1;
        $id = $counter++;

        return User::query()->create([
            'first_name' => 'User',
            'middle_initial' => null,
            'last_name' => (string) $id,
            'extension_name' => null,
            'username' => 'user' . $id,
            'email' => 'user' . $id . '@example.test',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function createEmployee(User $user): Employee
    {
        return Employee::query()->create([
            'user_id' => $user->id,
            'Employee_Number' => 'EMP-' . $user->id,
        ]);
    }
}

