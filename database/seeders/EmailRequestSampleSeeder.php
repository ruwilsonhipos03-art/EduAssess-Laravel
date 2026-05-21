<?php

namespace Database\Seeders;

use App\Models\EmailRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailRequestSampleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()
            ->where('role', 'student')
            ->orderBy('id')
            ->first();

        $fullName = $user
            ? trim(implode(' ', array_filter([
                (string) $user->first_name,
                (string) ($user->middle_name ?? ''),
                (string) $user->last_name,
                (string) ($user->extension_name ?? ''),
            ])))
            : 'Juan Dela Cruz';

        $email = $user?->email ?: 'student@example.com';
        $userId = $user?->id;

        $samples = [
            [
                'subject' => 'Entrance Exam Schedule Details',
                'message' => implode("\n", [
                    "Hello {$fullName},",
                    '',
                    'Your entrance exam schedule has been confirmed.',
                    '',
                    'Please review the schedule details below and keep them for your reference.',
                    '',
                    'Application ID: APP-2026-0001',
                    'Selected Programs:',
                    '1. Bachelor of Science in Information Technology',
                    '2. Bachelor of Science in Criminology',
                    '3. Bachelor of Elementary Education',
                    'Exam: Entrance Examination',
                    'Exam Type: Entrance',
                    'Date: May 25, 2026',
                    'Time: 09:00 AM',
                    'Location: Testing Center Room 101',
                ]),
                'status' => 'success',
            ],
            [
                'subject' => 'Entrance Exam Result',
                'message' => implode("\n", [
                    "Congratulations, {$fullName}!",
                    '',
                    'We are pleased to inform you that you passed the entrance examination.',
                    'Your entrance exam score is: 86',
                    '',
                    'Program Choices:',
                    '1st Choice: Bachelor of Science in Information Technology',
                    '2nd Choice: Bachelor of Science in Criminology',
                    '3rd Choice: Bachelor of Elementary Education',
                    '',
                    'Recommended Programs:',
                    '1st: Bachelor of Science in Information Technology',
                    '2nd: Bachelor of Science in Computer Science',
                    '3rd: Bachelor of Science in Information Systems',
                    '',
                    'Screening Exam Schedule:',
                    'Exam: BSIT Screening Examination',
                    'Date: May 28, 2026',
                    'Time: 10:00 AM',
                    'Location: Computer Laboratory 1',
                    '',
                    'Congratulations once again, and we wish you success in the next step of the admission process.',
                ]),
                'status' => 'success',
            ],
            [
                'subject' => 'Entrance Exam Result',
                'message' => implode("\n", [
                    "Dear {$fullName},",
                    '',
                    'Thank you for taking the entrance examination.',
                    'We regret to inform you that you did not meet the passing score for the entrance exam.',
                    'Entrance Exam Score: 68',
                    '',
                    'You may contact the admissions office for guidance on the next available application period.',
                ]),
                'status' => 'failed',
                'error_message' => 'Sample failed request for email request status preview.',
            ],
            [
                'subject' => 'Screening Exam Result',
                'message' => implode("\n", [
                    "Congratulations, {$fullName}!",
                    '',
                    'We are pleased to inform you that you passed the screening examination.',
                    'Screening Exam: BSIT Screening Examination',
                    'Program: Bachelor of Science in Information Technology',
                    'Screening Exam Score: 82',
                    '',
                    'You may now proceed with the next step of the admission process for your qualified program.',
                ]),
                'status' => 'success',
            ],
            [
                'subject' => 'Screening Exam Result',
                'message' => implode("\n", [
                    "Dear {$fullName},",
                    '',
                    'Thank you for taking the screening examination.',
                    'We regret to inform you that you did not meet the passing score for this screening exam.',
                    'Screening Exam: BSIT Screening Examination',
                    'Screening Exam Score: 70',
                    'Screening Choice Rank: 1',
                    '',
                    'Next Eligible Screening Option:',
                    'Rank: 2',
                    'Program: Bachelor of Science in Criminology',
                    'You are now eligible to be scheduled for this next option.',
                    '',
                    'Recommended Programs:',
                    '1st: Bachelor of Science in Information Technology',
                    '2nd: Bachelor of Science in Criminology',
                    '3rd: Bachelor of Elementary Education',
                    '',
                    'Please go to the college office based on your recommended programs for your scheduling, or take the exam there if instructed by the college.',
                ]),
                'status' => 'success',
            ],
        ];

        foreach ($samples as $sample) {
            EmailRequest::query()->updateOrCreate(
                [
                    'email' => $email,
                    'subject' => $sample['subject'],
                    'message' => $sample['message'],
                ],
                [
                    'user_id' => $userId,
                    'full_name' => $fullName,
                    'status' => $sample['status'],
                    'error_message' => $sample['error_message'] ?? null,
                    'sent_at' => $sample['status'] === 'success' ? now() : null,
                ]
            );
        }
    }
}
