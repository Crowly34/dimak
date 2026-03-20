# Google Sheets Sync — Setup Instructions

## Overview

One-way, read-only sync: Google Sheets → Dimak.
The app reads your parents' spreadsheet every few minutes and imports new orders.
The spreadsheet is never modified by the app.

**Cost**: $0. Google Sheets API is free (60 requests/min quota, way more than enough).

---

## Step 1: Google Cloud Project

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Sign in with your Google account
3. Click the project dropdown (top bar) → **New Project**
4. Name: `dimak` → **Create**
5. Make sure the new `dimak` project is selected in the dropdown

## Step 2: Enable APIs

1. Go to **APIs & Services → Library** (left sidebar)
2. Search for **Google Sheets API** → click it → **Enable**
3. Search for **Google Drive API** → click it → **Enable**

Both are needed — Sheets for reading data, Drive for accessing the spreadsheet by ID.

## Step 3: Create Service Account

1. Go to **IAM & Admin → Service Accounts** (left sidebar)
2. Click **+ Create Service Account**
3. Name: `dimak-sheets-reader`
4. Click **Done** (no roles needed — it only reads sheets shared with it)

## Step 4: Download JSON Key

1. Click the service account email you just created
2. Go to the **Keys** tab
3. Click **Add Key → Create new key**
4. Select **JSON** → **Create**
5. A `.json` file downloads automatically — **save it, you can't download it again**

## Step 5: Share Spreadsheet with Service Account

1. Open your parents' Google Spreadsheet
2. Click **Share** (top right)
3. Paste the service account email (looks like `dimak-sheets-reader@dimak-XXXXX.iam.gserviceaccount.com`)
4. Set permission to **Viewer** (read-only — the app can never modify the sheet)
5. Uncheck "Notify people" → **Share**

## Step 6: Get Spreadsheet ID

From the spreadsheet URL:
```
https://docs.google.com/spreadsheets/d/SPREADSHEET_ID_IS_HERE/edit
```
Copy the long string between `/d/` and `/edit`.

## Step 7: Configure Dimak

1. Copy the JSON key file to the project:
   ```bash
   cp ~/Downloads/dimak-XXXXX-abc123.json storage/app/google-service-account.json
   ```

2. Add to your `.env`:
   ```env
   GOOGLE_SERVICE_ENABLED=true
   GOOGLE_SERVICE_ACCOUNT_JSON_LOCATION=storage/app/google-service-account.json
   GOOGLE_SHEETS_SPREADSHEET_ID=your_spreadsheet_id_here
   GOOGLE_SHEETS_SHEET_NAME=Sheet1
   ```

3. Make sure `storage/app/google-service-account.json` is in `.gitignore` (it contains secrets).

---

## Security Notes

- The service account has **Viewer** access only — physically cannot edit the spreadsheet
- The JSON key file should never be committed to git
- The Google Sheets API is free — no billing account required for read-only access
- Quota: 60 requests/minute per user (a sync every 5 min = 0.2 req/min, nowhere near the limit)
