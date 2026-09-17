# Acceptance Letter & Certificate Designer Plugin for OJS 3.5

A comprehensive plugin for **Open Journal Systems (OJS) 3.5** that allows journal managers and editors to design, customize, issue, verify, and download official PDF Acceptance Letters and Certificates for accepted submissions.

---

## Features

* **Visual Letter & Certificate Designer:** Customize layout, paper size (A4 / Letter), orientation (Portrait / Landscape), language/RTL, header logos, editor signatures, and official seals/stamps.
* **Dynamic Placeholders:** Supports dynamic manuscript tokens (`{$submissionId}`, `{$articleTitle}`, `{$authorsList}`, `{$primaryAuthor}`, `{$journalName}`, `{$issn}`, `{$dateAccepted}`, `{$certificateNumber}`, `{$editorName}`, `{$qrCode}`).
* **Editorial Workflow Integration:** Automatically injects an action banner directly onto manuscript workflow pages (`/workflow/access/{submissionId}`) with a one-click PDF generation button.
* **Public QR Code Verification:** Generates cryptographically secure verification tokens and embedded QR codes that lead to a public certificate validation page (`/index.php/{journal}/acceptance/verify/{token}`).
* **Multi-Language Support:** Full translation support for English and Arabic (RTL layout ready).

---

## Directory Layout

```text
plugins/generic/acceptanceLetter/
├── AcceptanceLetterPlugin.php          # Main plugin class (hooks, routing, settings)
├── version.xml                         # Plugin descriptor
├── composer.json                       # Dependencies (dompdf, chillerlan/php-qrcode)
├── classes/
│   ├── migration/
│   │   └── AcceptanceLetterSchemaMigration.php  # Database migrations
│   ├── model/
│   │   ├── AcceptanceTemplate.php      # Template settings model
│   │   └── IssuedCertificate.php       # Issued certificate tokens model
│   ├── service/
│   │   ├── CertificatePdfService.php   # PDF engine & dynamic token substitution
│   │   └── VerificationService.php     # QR code and token verification generator
│   └── handlers/
│       ├── AcceptanceLetterSettingsHandler.php # Settings management handler
│       ├── AcceptanceLetterWorkflowHandler.php # PDF generation & workflow handler
│       └── AcceptanceLetterVerifyHandler.php   # Public verification endpoint
├── templates/
│   ├── settings.tpl                    # Visual designer settings interface
│   ├── workflowModal.tpl               # Workflow modal template
│   └── verify.tpl                      # Public verification result page
└── locale/
    ├── en/locale.po                    # English localization
    └── ar/locale.po                    # Arabic localization (العربية)
```

---

## Installation Guide

### Step 1: Copy Plugin into OJS
Place the plugin directory into your OJS installation under `plugins/generic/acceptanceLetter`:

```bash
cp -r /path/to/AcceptanceCerificateDesigner <path_to_ojs>/plugins/generic/acceptanceLetter
```

> **Note:** Ensure the destination directory name is strictly `acceptanceLetter`.

### Step 2: Install Composer Dependencies
Run composer inside the plugin directory to install the PDF and QR code packages:

```bash
cd <path_to_ojs>/plugins/generic/acceptanceLetter
composer install --no-dev --optimize-autoloader
```

### Step 3: Run Database Migrations
The plugin automatically provisions required database tables (`acceptance_templates` and `acceptance_issued_letters`) upon first access to Settings. Alternatively, you can run the OJS migration tool:

```bash
# From your OJS root directory:
php tools/upgrade.php upgrade
```

### Step 4: Set Directory Permissions
Ensure that your OJS `public/` directory (where logos, signatures, and stamps are saved) is writable by the web server user:

```bash
chown -R www-data:www-data public/
chmod -R 775 public/
```

---

## Configuration & Template Design

1. Log in to OJS as a **Journal Manager** or **Site Administrator**.
2. Go to **Settings** > **Website** > **Plugins** > **Installed Plugins**.
3. Under **Generic Plugins**, locate **Acceptance Letter & Certificate Designer** and check the box to **Enable** it.
4. Click the blue expand arrow next to the plugin name and select **Settings**.
5. Customize:
   * **Paper Dimensions:** Select `A4` or `US Letter`.
   * **Orientation:** Choose `Portrait` or `Landscape`.
   * **Language / Direction:** Choose English (LTR) or Arabic (RTL).
   * **Dynamic Tokens:** Click on token chips (e.g. `{$articleTitle}`, `{$authorsList}`, `{$qrCode}`) to insert them into your letter body.
   * **Assets:** Upload your journal's header logo, official seal/stamp, and editor signature.
6. Click **Save**.

---

## How to Issue & Download Certificates

### Method 1: Via the Editorial Workflow UI
1. Navigate to **Submissions** in the editorial dashboard.
2. Open any accepted manuscript (e.g., in **Copyediting**, **Production**, or **Archives**).
3. At the top of the workflow page, you will see the **Acceptance Letter & Certificate Designer** action banner:
   * Click **[ Generate & Download PDF ]**.
4. The signed acceptance certificate will be generated on the fly and downloaded directly to your computer.

### Method 2: Via Direct URL
As an authorized Editor or Administrator, you can download the certificate directly by navigating to:

```text
https://your-journal-domain.com/index.php/{journal}/acceptanceWorkflow/downloadPdf?submissionId={SUBMISSION_ID}
```

---

## Public Certificate Verification

Every generated certificate includes:
1. A unique **Certificate ID** (e.g., `ACC-2026-1-45-A1B2`).
2. An embedded **scannable QR Code** and public validation URL.

When an author, institution, or third-party scans the QR code or visits:
```text
https://your-journal-domain.com/index.php/{journal}/acceptance/verify/{TOKEN}
```
They will see an official confirmation page displaying:
* Authenticity status (Authentic vs. Invalid).
* Manuscript Title and Authors.
* Manuscript Submission ID.
* Official Date of Issuance.
* Issuing Journal Name.

---

## Troubleshooting

* **404 on Settings modal:** Ensure the plugin files are updated and template caching is cleared:
  ```bash
  rm -rf <path_to_ojs>/cache/t_compile/*
  ```
* **Class "PKP\file\PublicFileManager" not found:** Ensure `AcceptanceLetterPlugin.php` references `APP\file\PublicFileManager`.
* **Missing tables error (SQLSTATE 42S02):** Opening the plugin Settings in the web UI will automatically run the schema migration, or execute `php tools/upgrade.php upgrade` in the terminal.
