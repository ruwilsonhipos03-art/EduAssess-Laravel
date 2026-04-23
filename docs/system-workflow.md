# EduAssess System Workflow

## 1) End-to-End Workflow (Whole System)

```mermaid
flowchart TD
    A[System Setup by Admin] --> B[Student Registers/Login]
    B --> C[Auto-assign to Earliest Available Entrance Schedule]
    C --> D[Exam Team Creates Entrance Exam + Answer Key]
    D --> E[Generate QR Answer Sheets PDF]
    E --> F[Student Takes Exam]
    F --> G[Examiner Uploads OMR Images]
    G --> H[Python OMR API Reads QR + Answers]
    H --> I[Laravel Scores + Stores Exam Results]
    I --> J{Score >= 75?}
    J -- No --> K[Student Sees Failed Result]
    J -- Yes --> L[Student Gets Program Recommendations]
    L --> M[Student Selects Top 3 Programs]
    M --> N[College Dean Assigns Screening Schedules]
    N --> O[Generate/Scan Screening Sheets]
    O --> P{Screening Passed?}
    P -- No --> Q[Try Next Ranked Program / Re-pick if all failed]
    P -- Yes --> R[Student Picks Final Program or Continue Next]
    R --> S[Program Assignment Finalized]
    S --> T[Admin/Dean/Instructor Reporting and Analytics]
```

## 2) Role Swimlane Workflow

```mermaid
flowchart LR
    subgraph Admin
      A1[Manage users, offices, colleges, programs, subjects]
      A2[Create entrance/other schedules]
      A3[View system-wide reports]
    end

    subgraph Student
      B1[Register/Login]
      B2[Take entrance exam]
      B3[View results]
      B4[Select top 3 programs]
      B5[Take screening exams]
      B6[Choose final program]
    end

    subgraph Examiner
      C1[Create exams + answer keys]
      C2[Generate answer sheets]
      C3[Scan OMR images]
      C4[Publish checked scores]
    end

    subgraph CollegeDean
      D1[Manage college subjects/students/instructors]
      D2[Assign students/instructors to subjects]
      D3[Create screening schedules]
      D4[Assign eligible students to screening schedules]
    end

    subgraph Instructor
      E1[Create term exams + answer keys]
      E2[Generate term QR sheets by subject]
      E3[Scan and check term sheets]
      E4[View class reports]
    end

    subgraph OMRService
      F1[Receive image]
      F2[Decode QR + marked answers]
      F3[Return parsed payload to Laravel API]
    end

    A1 --> C1
    A2 --> B1
    B2 --> C3
    C3 --> F1 --> F2 --> F3 --> C4
    C4 --> B3 --> B4 --> D3 --> D4 --> B5 --> B6
    D1 --> E1
    E1 --> E2 --> E3 --> E4
    A3 --> E4
```

## 3) Core Operational Phases

1. Foundation Setup
- Admin configures master data: users, colleges/departments, offices, programs, program requirements, subjects, and schedules.

2. Authentication and Access
- Users authenticate via Sanctum token (`/api/login`).
- Role-based routing grants access to `admin`, `college_dean`, `entrance_examiner`, and `instructor` modules.
- Student operations are API-driven (registration, schedules, reports, recommendations).

3. Entrance Exam Lifecycle
- Student registration auto-books the earliest available entrance schedule.
- Examiner/dean/instructor can create exams and answer keys.
- Answer sheets are generated with QR payloads and printed as PDFs.
- After exam, OMR scan checks sheets and stores:
  - `answer_sheets.status = checked`
  - `answer_sheets.total_score`
  - subject scores in `exam_results`

4. Recommendation and Screening Lifecycle
- If entrance score is passing (`>= 75`), student can open recommendations.
- System computes qualified programs using `program_requirements` weighted by subject scores.
- Student selects top 3 ranked programs (`recommendations.type = student_choice`).
- Deans schedule and assign eligible students to screening exam slots.
- Screening results enforce progression rules:
  - Pass rank N: student decides `pick` final program or `continue` to higher rank.
  - Fail: move to next ranked option.
  - Fail all selected programs: repick flow opens.

5. Instructor Term Exam Lifecycle
- Instructor creates term exam and subject mappings.
- Instructor generates subject-based QR sheets for assigned students.
- Instructor scans via term OMR endpoint; scores are stored similarly in answer sheets/exam results.

6. Reporting and Monitoring
- Admin: users, activities, scheduled students, exam reports.
- College Dean/Instructor/Entrance: exam result reports and analysis views.
- Student: personal checked results and recommendation state.

## 4) Main Data Objects and State Transitions

### Key Entities
- `users` (roles)
- `students`, `employees`
- `exams`, `exam_subjects`, `answer_keys`
- `exam_schedules`, `student_exam_schedules`
- `answer_sheets`, `exam_results`
- `programs`, `program_requirements`, `recommendations`

### Common State Transitions

```mermaid
stateDiagram-v2
    [*] --> scheduled
    scheduled --> generated: sheet created/printed
    generated --> checked: OMR scan + scoring
    checked --> [*]
```

```mermaid
stateDiagram-v2
    [*] --> no_selection
    no_selection --> top3_selected
    top3_selected --> screening_in_progress
    screening_in_progress --> passed_program
    screening_in_progress --> failed_program
    passed_program --> final_program_picked
    passed_program --> continue_next_screening
    failed_program --> repick_allowed: if all selected programs failed
```

## 5) API-Level Workflow Map (Simplified)

1. Auth + Profile
- `/api/login`, `/api/register`, `/api/logout`, `/api/profile/*`

2. Exam Authoring and Sheets
- `/api/exams`
- `/api/answer-keys`
- `/api/answer-sheets`
- `/api/answer-sheets/generate`
- `/api/answer-sheets/generate-term`

3. OMR Checking
- Entrance/screening: `/api/entrance/omr/check`
- Term exam: `/api/instructor/omr/check-term`

4. Scheduling and Assignment
- Admin: `/api/admin/exam-schedules`
- Dean screening schedules + assignments:
  - `/api/college_dean/screening-schedules`
  - `/api/college_dean/screening-schedules/assign-students`

5. Recommendations and Student Decisions
- `/api/student/program-recommendations`
- `/api/student/program-recommendations/select`
- `/api/student/program-recommendations/decision`

6. Reports
- Admin reports: `/api/admin/*`
- Entrance reports: `/api/entrance/reports/*`
- Student reports: `/api/student/reports`

---

This workflow is based on the current controllers/routes in this repository and can be used directly for thesis documentation, onboarding, or architecture reviews.
