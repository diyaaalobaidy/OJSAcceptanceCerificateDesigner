# Acceptance Letter & Certificate Designer Plugin for OJS 3.4 & 3.5

A complete, production-ready plugin for **Open Journal Systems (OJS) 3.4 and 3.5** that allows journal managers and editors to design, customize, issue, verify, download, and email official PDF Acceptance Letters and Certificates for accepted submissions.

---

## Key Features

* **Visual Letter & Certificate Designer:**
  * Customize document layout, page dimensions (`A4` or `US Letter`), and page orientation (`Portrait` or `Landscape`).
  * Full language and layout direction support for English (LTR) and Arabic (RTL).
  * Upload official assets: **Header Logo**, **Editor Signature**, and **Official Seal / Stamp**.
  * Dynamic, proportional image bounding boxes that strictly preserve natural aspect ratios without squishing or stretching.
  * Print-perfect A4 single-page formatting engineered to eliminate unwanted page overflows.

* **Extensive Dynamic Placeholders:**
  * Inject live manuscript metadata into templates via clickable tokens:
    * Manuscript data: `{$submissionId}`, `{$articleTitle}`, `{$authorsList}`, `{$primaryAuthor}`, `{$sectionTitle}`, `{$doi}`, `{$dateAccepted}`
    * Journal & Issuer data: `{$journalName}`, `{$journalInitials}`, `{$issn}`, `{$editorName}`, `{$editorRole}`, `{$dateIssued}`
    * Security & Verification: `{$certificateNumber}`, `{$verificationUrl}`, `{$qrCode}`
    * Media elements: `{$headerLogo}`, `{$editorSignature}`, `{$journalSeal}`

* **Editorial Workflow Integration:**
  * Seamlessly injects an action banner directly into editorial manuscript workflow screens (`/workflow/access/{submissionId}`) for accepted submissions.
  * Interactive modal allowing editors to preview issuance details, author contact info, and issue certificates on demand.

* **One-Click Email to Author:**
  * Direct email delivery to the primary/corresponding author straight from the workflow modal.
  * Automatically generates and attaches the official signed PDF certificate to the email.

* **Public Verification via Scannable QR Code:**
  * Issues cryptographically secure, unique verification tokens (e.g., `ACC-2026-1-45-A1B2`).
  * Automatically renders high-resolution QR codes pointing to a dedicated public verification endpoint (`/index.php/{journal}/acceptance/verify/{token}`).
  * Public validation page verifies certificate authenticity, displaying manuscript title, author list, issuance date, and issuing journal name.

* **Robust PDF Generation Engine:**
  * Built on Dompdf and php-qrcode with base64 data-URI image embedding to ensure flawless rendering across varying server chroot, container, and reverse-proxy configurations.

---

## Directory Structure

```text
plugins/generic/acceptanceLetter/
├── AcceptanceLetterPlugin.php              # Main plugin class (hooks, routing, lifecycle)
├── version.xml                             # OJS plugin descriptor
├── composer.json                           # Dependencies (dompdf/dompdf, chillerlan/php-qrcode)
├── classes/
│   ├── migration/
│   │   └── AcceptanceLetterSchemaMigration.php  # Database migrations (templates & issued logs)
│   ├── model/
│   │   ├── AcceptanceTemplate.php          # Eloquent model for template configuration
│   │   └── IssuedCertificate.php           # Eloquent model for issued certificate tokens
│   ├── service/
│   │   ├── CertificatePdfService.php       # Dompdf layout engine, scaling & token parser
│   │   └── VerificationService.php         # Secure token generation & QR code renderer
│   └── handlers/
│       ├── AcceptanceLetterSettingsHandler.php # Journal settings management
│       ├── AcceptanceLetterWorkflowHandler.php # PDF download, modal & email delivery
│       └── AcceptanceLetterVerifyHandler.php   # Public verification portal
├── templates/
│   ├── settings.tpl                        # Visual template designer & asset manager
│   ├── workflowModal.tpl                   # Editorial modal (Download PDF & Send Email)
│   └── verify.tpl                          # Public certificate authenticity portal
├── js/
│   └── workflow.js                         # Workflow UI banner injection script
└── locale/
    ├── en/locale.po                        # English translations
    └── ar/locale.po                        # Arabic translations (العربية - RTL ready)
```

---

## Requirements

* **OJS Version:** OJS 3.4.x or 3.5.x
* **PHP:** PHP 8.1, 8.2, or 8.3
* **PHP Extensions:** `gd` or `imagick`, `mbstring`, `openssl`, `xml`, `pdo`
* **Composer:** Required for initial package installation

---

## Installation Guide

### Step 1: Deploy Plugin to OJS Directory
Clone the repository directly into your OJS installation under `plugins/generic/acceptanceLetter` (the target folder **must** be named `acceptanceLetter`):

```bash
# Navigate to your OJS installation directory
cd <path_to_ojs>/plugins/generic

# Clone the repository into the 'acceptanceLetter' folder
git clone https://github.com/diyaaalobaidy/OJSAcceptanceCerificateDesigner acceptanceLetter
```

> **Note:** If you already downloaded or extracted the folder locally, ensure the destination directory inside `plugins/generic/` is named exactly `acceptanceLetter`:
> ```bash
> cp -r /path/to/AcceptanceCerificateDesigner <path_to_ojs>/plugins/generic/acceptanceLetter
> ```

### Step 2: Install Composer Dependencies
Navigate into the plugin directory and run Composer to install `dompdf` and `php-qrcode`:

```bash
cd <path_to_ojs>/plugins/generic/acceptanceLetter
composer install --no-dev --optimize-autoloader
```

### Step 3: Run Database Migrations
The plugin automatically provisions its required database tables (`acceptance_templates` and `acceptance_issued_letters`) upon first access to the Settings page. Alternatively, you can run the standard OJS upgrade tool from your OJS root:

```bash
cd <path_to_ojs>
php tools/upgrade.php upgrade
```

### Step 4: Verify Directory Permissions
Ensure that your OJS `public/` directory (where journal logos, editor signatures, and seals are stored) is writable by your web server:

```bash
chown -R www-data:www-data public/
chmod -R 775 public/
```

### Step 5: Clear Template Cache
Clear compiled templates to ensure OJS detects new handlers and templates:

```bash
rm -rf cache/t_compile/* cache/_db/*
```

---

## Configuration & Template Customization

1. Log in to OJS as a **Journal Manager** or **Site Administrator**.
2. Navigate to **Settings** > **Website** > **Plugins** > **Installed Plugins**.
3. Locate **Acceptance Letter & Certificate Designer** under **Generic Plugins** and check the box to **Enable** it.
4. Click the blue expansion arrow next to the plugin name and click **Settings**.
5. Customize your certificate template:
   * **Paper Size & Orientation:** Choose between `A4` or `Letter`, and `Portrait` or `Landscape`.
   * **Language & Layout Direction:** Select `English (LTR)` or `Arabic (RTL)`.
   * **Upload Assets:**
     * **Official Header Logo** (Recommended: PNG, JPG, or SVG).
     * **Editor Signature Image** (Recommended: transparent PNG).
     * **Journal Official Seal / Stamp** (Recommended: transparent PNG or SVG).
   * **Letter Body Template:** Edit the HTML template text and click any token chip above the editor to insert dynamic placeholders (e.g. `{$articleTitle}`, `{$authorsList}`, `{$dateAccepted}`, `{$qrCode}`).
6. Click **Save Settings**.

---

## Editorial Usage

### 1. Generating and Downloading Certificates
1. In the OJS editorial dashboard, navigate to **Submissions** and select any accepted manuscript (in **Copyediting**, **Production**, or **Scheduled / Published**).
2. At the top of the workflow page, click **Issue Acceptance Letter & Certificate** on the action banner.
3. In the modal:
   * Click **📄 Generate & Download PDF** to download the signed PDF certificate immediately.

### 2. Emailing the Certificate Directly to Authors
1. Open the workflow modal for the accepted manuscript.
2. Click **✉️ Send Letter to Author**.
3. The plugin generates the official PDF certificate with a unique verification token and sends an email directly to the primary/corresponding author with the certificate attached.

### 3. Direct URL Download (Authorized Editors)
Authorized journal managers and editors can also download certificates directly via URL:
```text
https://your-domain.com/index.php/{journal}/acceptanceWorkflow/downloadPdf?submissionId={SUBMISSION_ID}
```

---

## Public Certificate Verification

Every issued certificate features an authentic cryptographic ID (e.g., `ACC-2026-1-102-C9D4`) and an embedded QR code.

When scanned by a phone camera or opened via browser:
```text
https://your-domain.com/index.php/{journal}/acceptance/verify/{TOKEN}
```

The portal displays:
* **Verification Status:** Official verification badge (Authentic vs. Invalid).
* **Article Details:** Title, Author(s), Submission ID, and Section.
* **Issuing Information:** Date of Issuance and Journal Name.

---

## Supported Dynamic Tokens (OJS 3.5 Documented Fields)

### Manuscript & Metadata
| Token | Description |
| :--- | :--- |
| `{$submissionId}` | Manuscript numeric submission ID (e.g. `1024`) |
| `{$articleTitle}` | Localized title of the manuscript |
| `{$articleFullTitle}` | Full title including prefix and subtitle |
| `{$articlePrefix}` | Title prefix (e.g., *The*, *A*) |
| `{$articleSubtitle}` | Manuscript subtitle |
| `{$articleAbstract}` | Clean plain-text abstract of the article |
| `{$sectionTitle}` | Journal section name (e.g., *Articles*, *Review Papers*) |
| `{$doi}` | Digital Object Identifier (DOI) assigned to the article |
| `{$pages}` | Page range in issue (e.g., `101-118`) |
| `{$articleNumber}` | Electronic article location / e-ID |
| `{$keywords}` | Article keywords separated by commas |
| `{$disciplines}` | Disciplines of study separated by commas |
| `{$subjects}` | Subject classification terms |
| `{$supportingAgencies}` | Research funding or supporting agency details |
| `{$licenseUrl}` | URL link to article distribution license (e.g. CC-BY) |
| `{$copyrightHolder}` | Name of designated copyright holder |
| `{$copyrightYear}` | Copyright year |
| `{$urlPublished}` | Public article landing page URL |

### Key Dates
| Token | Description |
| :--- | :--- |
| `{$dateAccepted}` | Formal editorial acceptance date (from decision records or publication) |
| `{$dateSubmitted}` | Date the manuscript was first submitted to the journal |
| `{$dateLastActivity}` | Timestamp of the latest recorded workflow activity |
| `{$datePublished}` | Date of publication (if already published or scheduled) |
| `{$dateIssued}` | Date this acceptance certificate/letter was generated |

### Authors & Affiliations
| Token | Description |
| :--- | :--- |
| `{$authorsList}` | Full list of author names separated by commas |
| `{$authorsAffiliations}` | All authors with their institutional affiliations |
| `{$primaryAuthor}` | First / primary corresponding author full name |
| `{$primaryAuthorEmail}` | Email address of the primary contact author |
| `{$primaryAuthorAffiliation}` | Institutional / academic affiliation of primary author |
| `{$primaryAuthorOrcid}` | ORCID identifier of primary author |

### Issue & Volume (When Scheduled / Published)
| Token | Description |
| :--- | :--- |
| `{$issueIdentification}` | Complete issue string (e.g., `Vol. 10 No. 2 (2025)`) |
| `{$issueTitle}` | Title of the assigned issue |
| `{$issueVolume}` | Issue volume number |
| `{$issueNumber}` | Issue number |
| `{$issueYear}` | Issue publication year |

### Journal & Publisher
| Token | Description |
| :--- | :--- |
| `{$journalName}` | Full name of the issuing journal |
| `{$journalInitials}` | Journal acronym or initials |
| `{$issn}` | Online or Print ISSN |
| `{$onlineIssn}` | Online ISSN |
| `{$printIssn}` | Print ISSN |
| `{$publisherInstitution}` | Publisher institution or sponsoring body |
| `{$journalUrl}` | Public homepage web address of the journal |
| `{$contactName}` | Principal journal contact name |
| `{$contactEmail}` | Principal journal contact email address |

### Certificate, Verification & Graphics
| Token | Description |
| :--- | :--- |
| `{$certificateNumber}` | Unique verification ID (e.g., `ACC-2026-1-45-A1B2`) |
| `{$editorName}` | Name of the issuing editor or contact person |
| `{$editorRole}` | Editorial title (e.g., *Editor-in-Chief*) |
| `{$verificationUrl}` | Plain text public verification web link |
| `{$qrCode}` | Scannable QR code element linking to verification page |
| `{$headerLogo}` | Official journal logo image element |
| `{$editorSignature}` | Official editor signature image element |
| `{$journalSeal}` | Journal official seal / stamp image element |

---

## Troubleshooting & FAQ

* **Images do not appear in the generated PDF:**
  The plugin automatically converts local files and URLs into base64 Data URIs, bypassing Dompdf chroot restrictions. Ensure your web server has read permissions to files in `public/journals/{journalId}/`.
* **Images look stretched or distorted:**
  The plugin automatically computes proportional width and height preserving natural aspect ratio based on bounding dimensions (Logo: $160 \times 60\text{px}$, Signature: $130 \times 45\text{px}$, Stamp: $75 \times 75\text{px}$). Clear your template compile cache if changes don't appear immediately:
  ```bash
  rm -rf cache/t_compile/*
  ```
* **PDF spans onto a second page:**
  Margins are set to `12mm 15mm 10mm 15mm` for A4 layout. If body content is unusually lengthy, adjust paragraph margins or font size in your custom HTML template within the Designer settings.
* **Plugin settings page gives 404 / Route not found:**
  Ensure the plugin folder inside `plugins/generic/` is named exactly `acceptanceLetter`. Then clear OJS cache:
  ```bash
  rm -rf cache/t_compile/* cache/_db/*
  ```

---

## License

This plugin is licensed under the **GNU General Public License v3.0 or later (GPL-3.0-or-later)**. See LICENSE for full terms.
