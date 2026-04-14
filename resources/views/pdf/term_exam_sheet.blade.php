<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Term Exam Answer Sheets</title>
    <style>
        @page {
            size: letter;
            margin: 1cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        h1, h2 {
            text-align: center;
            margin: 5px 0;
        }

        .page-break {
            page-break-after: always;
        }

        .header {
            position: relative;
            margin-bottom: 10px;
        }

        .qr {
            position: absolute;
            left: 0;
            top: 0;
            text-align: center;
        }

        .qr img {
            width: 100px;
            height: 100px;
        }

        .qr-label {
            font-size: 9px;
            margin-top: 2px;
        }

        .title-section {
            text-align: center;
            margin: 0 auto;
        }

        .info-fields {
            margin-top: 100px;
            border: 1px solid #000;
            padding: 6px;
            font-size: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .info-row label {
            width: 160px;
            font-weight: bold;
        }

        .info-value {
            border-bottom: 1px solid #000;
            min-width: 180px;
            height: 12px;
            padding-bottom: 5px;
            display: inline-block;
        }

        .bubbles {
            border: 1px solid #000;
            padding: 10px;
            margin-top: 10px;
        }

        .col {
            width: 23%;
            float: left;
            margin-right: 2%;
        }

        .col:last-child { margin-right: 0; }

        .question {
            margin: 3px 0;
            white-space: nowrap;
        }

        .qnum {
            display: inline-block;
            width: 20px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .options {
            display: inline-block;
            margin-top: 3px;
            vertical-align: top;
        }

        .bubble {
            display: inline-block;
            text-align: center;
            line-height: 16px;
            width: 17px;
            height: 17px;
            margin-right: 2px;
            border: 1px solid #000;
            border-radius: 50%;
            font-size: 11px;
            font-weight: bold;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .footer {
            margin-top: 20px;
            text-align: left;
            font-size: 9px;
        }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        <div class="header">
            <div class="qr">
                <img src="data:{{ $sheet['sheetQrMime'] ?? 'image/png' }};base64,{{ $sheet['sheetQr'] }}" width="100" height="100">
                <div class="qr-label">Sheet Code</div>
            </div>

            <div class="title-section">
                <h1 style="font-size: 16px;">Term Exam</h1>
                <h2>Answer Sheet</h2>
            </div>
        </div>

        <div class="info-fields">
            <div class="info-row">
                <label>Student Name:</label>
                <span class="info-value">{{ $sheet['student_name'] ?? '' }}</span>
                <label>Student Number:</label>
                <span class="info-value">{{ $sheet['student_number'] ?? '' }}</span>
            </div>
            <div class="info-row">
                <label>Exam Title:</label>
                <span class="info-value">{{ $sheet['exam_title'] ?? '' }}</span>
                <label>Subject:</label>
                <span class="info-value">{{ $sheet['subject_name'] ?? '' }}</span>
            </div>
        </div>

        <h2 style="margin-top:5px;">Answer Sheet</h2>
        <div class="bubbles clearfix">
            @for ($col = 0; $col < 4; $col++)
                <div class="col">
                    @for ($i = 1 + ($col * 25); $i <= ($col + 1) * 25; $i++)
                        <div class="question">
                            <div class="qnum">{{ $i }}.</div>
                            <div class="options">
                                @foreach (['A','B','C','D','E'] as $opt)
                                    <div class="bubble">{{ $opt }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            @endfor
        </div>

        <div class="footer">
            <p>Examinee's Signature: ______________________________ Date: ________________</p>
            <p style="padding-left:110px;">Signature over Printed Name</p>
        </div>

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
