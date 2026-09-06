<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            padding: 24px 20px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .body {
            background: #ffffff;
            padding: 24px 20px;
            border-radius: 0 0 12px 12px;
        }

        .icon {
            font-size: 40px;
            text-align: center;
            margin-bottom: 12px;
        }

        .message {
            font-size: 15px;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background: #0d6efd;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title }}</h1>
        </div>
        <div class="body">
            <div class="icon">
                {{ $type === 'deadline' ? '📅' : ($type === 'issue' ? '⚠️' : ($type === 'equipment' ? '🧯' : '🔔')) }}
            </div>
            <div class="message">{{ $message }}</div>
            @if ($url)
                <div style="text-align: center;">
                    <a class="btn" href="{{ $url }}">Visualizza nel backoffice</a>
                </div>
            @endif
        </div>
        <div class="footer">
            <p>Documento generato automaticamente da CV Backoffice</p>
        </div>
    </div>
</body>

</html>
