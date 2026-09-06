<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invito al gruppo</title>
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

        .message {
            font-size: 15px;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .code-box {
            background: #f0f5ff;
            border: 2px dashed #0d6efd;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
            letter-spacing: 4px;
            margin-bottom: 20px;
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
            <h1>Invito al gruppo {{ $groupName }}</h1>
        </div>
        <div class="body">
            <div class="message">
                Sei stato invitato a entrare nel gruppo <strong>{{ $groupName }}</strong> su CV Backoffice.
                <br><br>
                Usa questo codice per entrare nel gruppo:
            </div>
            <div class="code-box">{{ $inviteCode }}</div>
            <div class="message">
                Accedi a CV Backoffice, vai nella sezione <strong>Gruppi</strong> e inserisci il codice per entrare.
            </div>
        </div>
        <div class="footer">
            <p>Documento generato automaticamente da CV Backoffice</p>
        </div>
    </div>
</body>

</html>
