<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exam Answer Sheets</title>
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

        /* HEADER */
        .header {
            position: relative;
            margin-bottom: 10px;
        }

        /* Left QR */
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

            /* Shading Instructions Box */
        .shading-instructions {
            position: absolute;
            right: 0;
            top: 0;
            border: 1px solid #000;
            padding: 5px;
            width: 150px;
            background-color: #fff;
        }



        /* Title stays centered between QR codes */
        .title-section {
            text-align: center;
            margin: 0 auto;
        }

        /* Info fields */
        .info-fields {
            margin-top: 40px;
            border: 1px solid #000;
            padding: 6px;
            font-size: 10px;
        }

        .info-row {
            white-space: nowrap;
            margin-bottom: 6px;
            font-size: 10px;
        }

        .info-cell {
            display: inline-block;
            vertical-align: bottom;
            margin-right: 4px;
        }

        .info-cell.name-first {
            width: 33%;
        }

        .info-cell.name-middle {
            width: 17%;
        }

        .info-cell.name-last {
            width: 24%;
        }

        .info-cell.extension {
            width: 11%;
        }

        .info-cell.program-prefix {
            width: 14%;
        }

        .info-cell.choice {
            width: 27%;
        }

        .info-row label {
            font-weight: bold;
            white-space: nowrap;
        }

        .field-value {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-height: 12px;
            padding: 0 2px 1px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .info-cell.name-first .field-value {
            width: 68%;
        }

        .info-cell.name-middle .field-value {
            width: 42%;
        }

        .info-cell.name-last .field-value {
            width: 62%;
        }

        .info-cell.extension .field-value {
            width: 44%;
        }

        .info-cell.choice .field-value {
            width: 54%;
        }

        /* Bubble Section */
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

        /* Footer */
        .footer {
            margin-top: 20px;
            text-align: left;
            font-size: 9px;
        }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        <!-- Header -->
        <div class="header">
            <div class="qr">
                <img src="data:{{ $sheet['sheetQrMime'] ?? 'image/png' }};base64,{{ $sheet['sheetQr'] }}" width="100" height="100">
                <div class="qr-label">Sheet Code</div>
            </div>

            <div class="title-section">
                <img src="{{ public_path('images/acc-logo.png') }}" alt="Acc Logo" style="width:50px; height:auto;">
                <h1 style="font-size: 16px;">Abuyog Community College</h1>
                <h2>Abuyog, Leyte</h2>
                <h2>{{ $sheet['sheet_title'] ?? 'COLLEGE ADMISSION TEST ANSWER SHEET' }}</h2>
            </div>

            <div class="shading-instructions">
                <img src="{{ public_path('images/shading-instructions.png') }}"
                    alt="Shading Instructions"
                    style="width:100%; height:auto; margin-bottom:5px;">
            </div>
        </div>

        <!-- Info fields -->
        <div class="info-fields">
            <div class="info-row">
                <div class="info-cell name-last">
                    <label>Last Name:</label>
                    <span class="field-value">{{ $sheet['last_name'] ?? '' }}</span>
                </div>
                <div class="info-cell name-first">
                    <label>First Name:</label>
                    <span class="field-value">{{ $sheet['first_name'] ?? '' }}</span>
                </div>
                <div class="info-cell name-middle">
                    <label>Middle Initial:</label>
                    <span class="field-value">{{ $sheet['middle_initial'] ?? '' }}</span>
                </div>
                <div class="info-cell extension">
                    <label>Suffix:</label>
                    <span class="field-value">{{ $sheet['extension_name'] ?? '' }}</span>
                </div>
            </div>
            @if(($sheet['show_program_choices'] ?? true))
                <div class="info-row">
                    <div class="info-cell program-prefix">
                        <label>Program Choice:</label>
                    </div>
                    <div class="info-cell choice">
                        <label>1st Choice:</label>
                        <span class="field-value">{{ $sheet['program_choices'][0] ?? '' }}</span>
                    </div>
                    <div class="info-cell choice">
                        <label>2nd Choice:</label>
                        <span class="field-value">{{ $sheet['program_choices'][1] ?? '' }}</span>
                    </div>
                    <div class="info-cell choice">
                        <label>3rd Choice:</label>
                        <span class="field-value">{{ $sheet['program_choices'][2] ?? '' }}</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Bubble grid -->
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

        <!-- Footer -->
        <div class="footer">
            <p>Examinee’s Signature: ______________________________ Date: ________________</p>
            <p style="padding-left:110px;">Signature over Printed Name</p>
        </div>

        <!-- Add page break between sheets -->
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
