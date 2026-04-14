<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scheduled Students List</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 11px;
        }

        .page {
            position: relative;
            padding: 0;
            background: #ffffff;
            min-height: 284mm;
            box-sizing: border-box;
        }

        .page-break {
            page-break-after: always;
        }

        .main-content {
            padding-bottom: 114px;
        }

        .header-image {
            width: 100%;
            margin-bottom: 4px;
        }

        .header-image img {
            display: block;
            width: 100%;
            height: auto;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
        }

        .meta-grid td {
            border: 1px solid #4b5563;
            vertical-align: top;
            padding: 0;
        }

        .report-title {
            text-align: center;
            font-weight: 800;
            color: #991b1b;
            font-size: 16px;
            line-height: 1.08;
        }

        .report-subtitle {
            text-align: center;
            font-style: italic;
            font-size: 10px;
            margin-top: 2px;
            font-weight: 700;
        }

        .report-box {
            padding: 5px 7px 6px;
        }

        .report-box .report-subtitle {
            font-size: 12px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .info-table td {
            border-bottom: 1px solid #4b5563;
            padding: 3px 6px;
            font-size: 15px;
        }

        .info-table tr:last-child td {
            border-bottom: 0;
        }

        .info-label {
            width: 30%;
            font-weight: 800;
            background: #ffffff;
            text-align: right;
            white-space: nowrap;
        }

        .info-value {
            font-weight: 800;
            font-size: 11px;
        }

        .student-columns {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .student-columns > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        .student-columns > tbody > tr > td:first-child {
            padding-right: 3px;
        }

        .student-columns > tbody > tr > td:last-child {
            padding-left: 3px;
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
        }

        .student-table th,
        .student-table td {
            border: 1px solid #4b5563;
            padding: 1px 2px;
        }

        .student-table th {
            background: #f3f4f6;
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            color: #111827;
            letter-spacing: 0.02em;
        }

        .student-table td {
            font-size: 12px;
            line-height: 1;
        }

        .student-table .no-row td {
            text-align: center;
            color: #6b7280;
            padding: 6px 4px;
        }

        .side-stack > * + * {
            margin-top: 2px;
        }

        .wear-box,
        .values-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .wear-box td,
        .values-grid td {
            border: 1px solid #666a6e;
            vertical-align: top;
            padding: 3px 4px;
        }

        .notes-title,
        .values-title {
            font-weight: 800;
            margin-bottom: 1px;
            text-transform: uppercase;
            color: #111827;
            font-size: 11px;
        }

        .alert-note {
            color: #dc2626;
            font-weight: 800;
            font-style: italic;
            margin-bottom: 2px;
            font-size: 11px;
        }

        .wear-list {
            margin: 0;
            padding-left: 12px;
            font-size: 9.4px;
            line-height: 1.02;
        }

        .wear-list li {
            margin-bottom: 1px;
        }

        .wear-sublist {
            margin: 1px 0 0 7px;
            padding-left: 9px;
            list-style-type: square;
            font-size: 11px;
            line-height: 1.01;
        }

        .wear-sublist li {
            margin-bottom: 1px;
        }

        .values-logo {
            width: 100px;
            text-align: center;
            vertical-align: middle !important;
            background: #f9fafb;
        }

        .values-logo img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .values-inner {
            width: 100%;
            border-collapse: collapse;
            height: 100%;
        }

        .values-inner td {
            border: 1px solid #4b5563;
            padding: 0;
            vertical-align: top;
        }

        .value-block {
            padding: 2px 4px 3px;
            min-height: 33px;
        }

        .value-heading {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 12px;
            margin-bottom: 1px;
            color: #111827;
        }

        .value-copy {
            font-style: italic;
            line-height: 1.06;
            font-size: 11px;
            color: #1f2937;
        }

        .value-line {
            border-top: 1px solid #4b5563;
        }

        .goals-list {
            margin: 0;
            padding-left: 0;
            list-style: none;
            line-height: 1.03;
            font-size: 11px;
        }

        .goals-list li {
            margin-bottom: 1px;
        }

        .footer {
            position: absolute;
            right: 10px;
            bottom: 4px;
            text-align: right;
            font-size: 8.5px;
            color: #4b5563;
        }

        .bottom-panel {
            position: absolute;
            left: 7px;
            right: 7px;
            bottom: 85px;
            height: 146px;
        }
    </style>
</head>
<body>
    @php
        $pagesData = $pages ?? [[
            'schedule' => $schedule ?? [],
            'semesterLabel' => $semesterLabel ?? '',
            'leftRows' => $leftRows ?? [],
            'rightRows' => $rightRows ?? [],
        ]];
    @endphp

    @foreach ($pagesData as $pageData)
        @php
            $scheduleData = $pageData['schedule'] ?? [];
            $semesterLine = trim((string) ($pageData['semesterLabel'] ?? ''));
            $leftList = array_values($pageData['leftRows'] ?? []);
            $rightList = array_values($pageData['rightRows'] ?? []);
            $reportTitleLines = array_values($pageData['report_title_lines'] ?? [
                'COLLEGE ADMISSION TEST',
                'LIST OF STUDENT APPLICANTS',
            ]);
            $dateTimeLine = '-';

            if (!empty($scheduleData['date'])) {
                $dateTimeLine = \Carbon\Carbon::parse($scheduleData['date'])->format('F j, Y');

                if (!empty($scheduleData['time'])) {
                    $dateTimeLine .= ' (' . \Carbon\Carbon::parse($scheduleData['time'])->format('D') . ') at ' . \Carbon\Carbon::parse($scheduleData['time'])->format('g:i A');
                }
            }
        @endphp
        <div class="page{{ !$loop->last ? ' page-break' : '' }}">
            <div class="main-content">
                <div class="header-image">
                    <img src="{{ public_path('images/header.png') }}" alt="ACC Header">
                </div>

                <table class="meta-grid">
                    <tr>
                        <td style="width: 50%;">
                            <div class="report-box">
                                <div class="report-title">
                                    <b>{{ $reportTitleLines[0] ?? 'COLLEGE ADMISSION TEST' }}</b>
                                    @if(!empty($reportTitleLines[1]))
                                        <br><b>{{ $reportTitleLines[1] }}</b>
                                    @endif
                                    @if(!empty($reportTitleLines[2]))
                                        <br><b>{{ $reportTitleLines[2] }}</b>
                                    @endif
                                </div>
                                <div class="report-subtitle">{{ $semesterLine !== '' ? strtoupper($semesterLine) : '-' }}</div>
                            </div>
                        </td>
                        <td style="width: 43%;">
                            <table class="info-table">
                                <tr>
                                    <td class="info-label">Batch No:</td>
                                    <td class="info-value">{{ $scheduleData['schedule_name'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Date &amp; Time:</td>
                                    <td class="info-value">{{ $dateTimeLine }}</td>
                                </tr>
                                <tr>
                                    <td class="info-label">Room:</td>
                                    <td class="info-value">{{ $scheduleData['location'] ?? '-' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="student-columns">
                    <tr>
                        <td>
                            <table class="student-table">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">#</th>
                                        <th style="width: 34%;">Lastname</th>
                                        <th style="width: 34%;">Firstname</th>
                                        <th style="width: 24%;">Middle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($leftList as $index => $row)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $row['last_name'] ?: '-' }}</td>
                                            <td>{{ $row['first_name'] ?: '-' }}</td>
                                            <td>{{ $row['middle_name'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr class="no-row">
                                            <td colspan="4">No students assigned.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </td>
                        <td>
                            <div class="side-stack">
                                <table class="student-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 8%;">#</th>
                                            <th style="width: 34%;">Lastname</th>
                                            <th style="width: 34%;">Firstname</th>
                                            <th style="width: 24%;">Middle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($rightList as $index => $row)
                                            <tr>
                                                <td>{{ 30 + $index + 1 }}</td>
                                                <td>{{ $row['last_name'] ?: '-' }}</td>
                                                <td>{{ $row['first_name'] ?: '-' }}</td>
                                                <td>{{ $row['middle_name'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr class="no-row">
                                                <td colspan="4">No students assigned for slots 31 to 40.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                <table class="wear-box">
                                    <tr>
                                        <td>
                                            <br/><div class="alert-note"><b>PLEASE ARRIVE 10 MINUTES BEFORE SCHEDULED EXAM.</b></div><br/>
                                            <div class="notes-title">WHAT TO WEAR/ BRING:</div><br/>
                                            <ul class="wear-list">
                                                <li>WHITE T-SHIRT, PANTS/SKIRTS &amp; SHOES</li>
                                                <li>BALLPEN</li>
                                                <li>
                                                    ENTRANCE CREDENTIALS
                                                    <br/><span style="font-style: italic;">(if available, can proceed to Enrollment after results)</span>
                                                    <ul class="wear-sublist">
                                                        <br /><li>SF9 (SHS Card)</li>
                                                        <li>Cert of Good Moral Character</li>
                                                        <li>Photocopy of SHS Diploma</li>
                                                        <li>PSA Authenticated Livebirth</li>
                                                        <li>Two (2) pcs 2X2 ID Picture (with name tag and white background)</li>
                                                        <li>Medical Certificate any Government Doctor</li>
                                                        <li>One (1) Long Expanding Envelope</li>
                                                        <li>Additional Program-Specific Requirements (if applicable):</li>
                                                        <li>Recent Police Clearance (for BSCrim)</li><br/><br/>
                                                    </ul>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="bottom-panel">
                <table class="values-grid">
                    <tr>
                        <td class="values-logo">
                            <img src="{{ public_path('images/acc-logo.png') }}" alt="ACC Logo">
                        </td>
                        <td style="width: 45%; padding: 0;">
                            <table class="values-inner">
                                <tr>
                                    <td>
                                        <div class="value-block">
                                            <div class="value-heading">VISION</div>
                                            <div class="value-copy">A transformative community college committed towards fostering inclusive, accessible, and sustainable education through research, innovation, service, and strategic linkages.</div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="value-line">
                                        <div class="value-block">
                                            <div class="value-heading">MISSION</div>
                                            <div class="value-copy">To empower individuals and strengthen communities by providing relevant and responsive curriculum that fosters innovative, lifelong learning, and meaningful impact through strategic partnerships.</div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="value-line">
                                        <div class="value-block">
                                            <div class="value-heading">CORE VALUES</div>
                                            <div class="value-copy">Excellence, Competence, Accountability, Resilience, Environmentally-responsive, Service-oriented (E-CARES)</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 40%;">
                            <div class="value-block" style="padding: 3px 4px 4px;">
                                <div class="value-heading">GOALS</div>
                                <ul class="goals-list">
                                    <li>Goal 1: Assure Quality and Inclusive Education</li>
                                    <li>Goal 2: Promote Technology and Innovation</li>
                                    <li>Goal 3: Promote Resilient and Sustainable Communities</li>
                                    <li>Goal 4: Adapt Social Inclusion and Local Economies</li>
                                    <li>Goal 5: Establish Strong Collaborations and Linkages</li>
                                    <li>Goal 6: Build Human Capital for Sustainable Growth and Development</li>
                                    <li>Goal 7: Strengthen Good Governance and Institutional Sustainability</li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">Scheduled Students Report</div>
        </div>
    @endforeach
</body>
</html>
