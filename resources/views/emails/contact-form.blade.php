<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau message Gotfit</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family:Arial, sans-serif; color:#111827;">
    <div style="max-width:680px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(15,23,42,0.08);">
            <div style="background:#111827; color:#ffffff; padding:28px 32px;">
                <h1 style="margin:0; font-size:24px;">Nouveau message depuis Gotfit</h1>
                <p style="margin:8px 0 0; color:#d1d5db;">Formulaire de contact webapp</p>
            </div>

            <div style="padding:32px;">
                <p>Un nouveau message a été envoyé depuis la page contact de Gotfit.</p>

                <p><strong>Nom :</strong> {{ $data['name'] }}</p>
                <p><strong>Email :</strong> {{ $data['email'] }}</p>
                <p><strong>Téléphone :</strong> {{ $data['phone'] ?? 'Non renseigné' }}</p>
                <p><strong>Sujet :</strong> {{ $data['subject'] }}</p>

                <div style="margin-top:24px;">
                    <h2 style="font-size:18px; margin:0 0 12px;">Message</h2>
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:18px; line-height:1.6; white-space:pre-line;">
                        {{ $data['message'] }}
                    </div>
                </div>
            </div>

            <div style="padding:18px 32px; background:#f9fafb; color:#6b7280; font-size:13px;">
                Email automatique envoyé par Gotfit.
            </div>
        </div>
    </div>
</body>
</html>
