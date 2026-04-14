<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Answer Key</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 24px;
            color: #111827;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 14px 0;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .col {
            width: 25%;
            vertical-align: top;
            padding-right: 16px;
        }
        .line {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <h1>{{ $answerKey->exam->Exam_Title ?? 'Answer Key' }}</h1>

    @php
        $answers = is_array($answerKey->answers ?? null) ? $answerKey->answers : [];
    @endphp

    <table class="grid">
        <tr>
            @for ($col = 0; $col < 4; $col++)
                <td class="col">
                    @for ($item = 1 + ($col * 25); $item <= 25 + ($col * 25); $item++)
                        <div class="line">
                            {{ $item }}. {{ strtoupper((string)($answers[(string)$item] ?? '-')) }}
                        </div>
                    @endfor
                </td>
            @endfor
        </tr>
    </table>
</body>
</html>
