<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f3f0;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #e2792d, #c2410c);
            color: white;
            padding: 24px 20px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            opacity: 0.9;
        }

        .body {
            background: #ffffff;
            padding: 8px 20px 20px;
            border-radius: 0 0 12px 12px;
        }

        .event {
            display: flex;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #eee;
        }

        .event:last-child {
            border-bottom: none;
        }

        .event-icon {
            font-size: 24px;
            line-height: 1;
            flex-shrink: 0;
        }

        .event-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 4px;
        }

        .event-message {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
            margin: 0 0 8px;
        }

        .btn {
            display: inline-block;
            background: #c2410c;
            color: #ffffff !important;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
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
            <h1>{{ config('app.name') }}</h1>
            <p>
                @if (count($events) === 1)
                    1 nuova notifica
                @else
                    {{ count($events) }} nuove notifiche
                @endif
            </p>
        </div>
        <div class="body">
            @foreach ($events as $event)
                <div class="event">
                    <div class="event-icon">{{ \App\Models\Notification::iconForType($event['type']) }}</div>
                    <div>
                        <p class="event-title">{{ $event['title'] }}</p>
                        <p class="event-message">{{ $event['body'] }}</p>
                        @if ($event['url'])
                            <a class="btn" href="{{ $event['url'] }}">Visualizza nel backoffice</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="footer">
            <p>Documento generato automaticamente da {{ config('app.name') }}</p>
        </div>
    </div>
</body>

</html>
