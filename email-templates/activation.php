<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
        }
        .content {
            padding: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Aktivera ditt konto</h1>
    </div>
    
    <div class="content">
        <p>Hej <?php echo $firstName; ?>!</p>
        
        <p>Tack för din registrering. För att aktivera ditt konto, klicka på knappen nedan:</p>
        
        <p style="text-align: center;">
            <a href="<?php echo $activationLink; ?>" class="button">Aktivera konto</a>
        </p>
        
        <p>Om knappen inte fungerar kan du kopiera och klistra in följande länk i din webbläsare:</p>
        <p><?php echo $activationLink; ?></p>
        
        <p>Länken är giltig i 24 timmar.</p>
    </div>
    
    <div class="footer">
        <p>Detta mail skickades eftersom du registrerade ett konto på vår tjänst. Om du inte har registrerat dig kan du ignorera detta mail.</p>
    </div>
</body>
</html>