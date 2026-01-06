<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de Senha - Red Devils</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #dc2626;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #dc2626;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #b91c1c;
        }
        .token-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc2626;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
        .warning {
            background-color: #fef3c7;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #f59e0b;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔴 Red Devils</h1>
        </div>
        
        <div class="content">
            <h2>Recuperação de Senha</h2>
            
            <p>Olá, <strong>{{ $player->name }}</strong>!</p>
            
            <p>Recebemos uma solicitação para redefinir a senha da sua conta. Se você não fez esta solicitação, ignore este email.</p>
            
            <p>Para redefinir sua senha, use o token abaixo:</p>
            
            <div class="token-box">
                <strong>Token de Recuperação:</strong><br>
                {{ $token }}
            </div>
            
            <p>Ou acesse o link direto (se seu front-end suportar):</p>
            
            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">Redefinir Senha</a>
            </div>
            
            <div class="warning">
                <strong>⚠️ Importante:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Este token expira em <strong>60 minutos</strong></li>
                    <li>Use o token apenas uma vez</li>
                    <li>Não compartilhe este token com ninguém</li>
                </ul>
            </div>
            
            <p>Se você não solicitou esta recuperação, pode ignorar este email com segurança.</p>
        </div>
        
        <div class="footer">
            <p>Este é um email automático, por favor não responda.</p>
            <p>&copy; {{ date('Y') }} Red Devils - Sistema de Gerenciamento de Peladas</p>
        </div>
    </div>
</body>
</html>
