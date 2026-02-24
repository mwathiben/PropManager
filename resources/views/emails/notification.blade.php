<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin: 0 0 15px;
            font-size: 15px;
        }
        .message-box {
            background-color: #f9fafb;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        .data-table td {
            font-size: 14px;
            color: #6b7280;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #667eea;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
        }
        .button:hover {
            background-color: #5568d3;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $subject }}</h1>
        </div>

        <div class="content">
            <p>Hello {{ $recipient->name }},</p>

            <div class="message-box">
                {!! nl2br(e($notificationBody)) !!}
            </div>

            @if(isset($data) && is_array($data) && count($data) > 0)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Details</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data as $key => $value)
                            @if(!is_array($value) && !is_object($value))
                                <tr>
                                    <td><strong>{{ ucwords(str_replace('_', ' ', $key)) }}</strong></td>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(isset($data['action_url']) && isset($data['action_text']))
                <p style="text-align: center; margin-top: 30px;">
                    <a href="{{ $data['action_url'] }}" class="button">{{ $data['action_text'] }}</a>
                </p>
            @endif

            <p style="margin-top: 30px;">
                If you have any questions, please don't hesitate to contact us.
            </p>

            <p>
                Best regards,<br>
                <strong>PropManager Team</strong>
            </p>
        </div>

        <div class="footer">
            <p>
                This is an automated message from your property management system.<br>
                Please do not reply to this email.
            </p>
            <p>
                <a href="{{ config('app.url') }}">Visit Dashboard</a>
                @if(isset($unsubscribeUrl))
                    | <a href="{{ $unsubscribeUrl }}">Manage Email Preferences</a>
                @endif
            </p>
        </div>
    </div>
</body>
</html>
