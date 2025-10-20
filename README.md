# Klassrumsverktyg On-Prem

## ✨ Funktioner

- Digital whiteboard med anpassningsbara begränsningar
- Brain breaks – små pauser integrerade i lektionerna
- Användarhantering (admin, editor, lärare, elev)
- Inställningar för:
  - Självregistrering (av/på)
  - Google OAuth-inloggning
  - reCAPTCHA-skydd
  - SMTP-konfiguration för e-post
- Dynamiska sidor (Om, Kontakt, Integritetspolicy, Villkor) via admin-gränssnitt
- Stöd för import av användare via CSV

## ⚙️ Teknisk information

- **Backend:** PHP 8 + MySQL/MariaDB
- **Frontend:** Tailwind CSS, Vanilla JS
- **E-post:** PHPMailer används för SMTP-integration
- **Installation:** Medföljer ett enkelt `install.php`-skript för att konfigurera databas och systeminställningar

## 📜 Licens

Projektet distribueras under [MIT-licensen](./LICENSE).

> 🔒 **Observera:** PHPMailer är inkluderat som ett beroende.  
> PHPMailer är också licensierat under LGPL-2.1 (eller nyare), vilket innebär att det är fullt kompatibelt att använda tillsammans med MIT-projekt.  
> Du behöver dock bibehålla PHPMailers egen licenstext om du distribuerar projektet.

## 🚀 Kom igång

1. Klona repot
2. Kör `install/install.php` för att konfigurera databas och inställningar
3. Logga in som admin och anpassa systemet

## 📄 Licenser & tredjepartsberoenden

Detta projekt distribueras under [MIT-licensen](./LICENSE).

Projektet inkluderar även tredjepartsbibliotek:

- **[PHPMailer](https://github.com/PHPMailer/PHPMailer)**  
  Licens: [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html) eller senare  

Se [NOTICE](./NOTICE) för detaljer.
