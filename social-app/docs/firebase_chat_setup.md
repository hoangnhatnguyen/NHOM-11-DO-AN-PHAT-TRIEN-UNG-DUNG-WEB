# Firebase Chat Setup

## 1) Enable services
- Firebase Authentication
- Cloud Firestore

> Free mode: Không dùng Firebase Storage để tránh yêu cầu nâng gói. Tệp đính kèm được upload lên server PHP (`public/uploads/chat`).

## 2) Web app config
Copy Firebase web config into `.env`:
- `FIREBASE_API_KEY`
- `FIREBASE_AUTH_DOMAIN`
- `FIREBASE_PROJECT_ID`
- `FIREBASE_MESSAGING_SENDER_ID`
- `FIREBASE_APP_ID`

## 3) Service account for custom token
From Firebase Console > Project settings > Service accounts:
- Generate private key JSON
- Put values into `.env`:
  - `FIREBASE_SERVICE_ACCOUNT_EMAIL` = `client_email`
  - `FIREBASE_SERVICE_ACCOUNT_PRIVATE_KEY` = `private_key` (keep `\n` escaped in one line)

Example:

```
FIREBASE_SERVICE_ACCOUNT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nABC...\n-----END PRIVATE KEY-----\n"
```

## 4) Firestore security rules
Use this baseline:

```rules
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /conversations/{conversationId} {
      allow read, write: if request.auth != null;

      match /messages/{messageId} {
        allow read, write: if request.auth != null;
      }

      match /attachments/{attachmentId} {
        allow read, write: if request.auth != null;
      }
    }

    match /user_presence/{uid} {
      allow read, write: if request.auth != null;
    }
  }
}
```

## 5) Firestore indexes
Create composite index:
- Collection: `conversations`
- Fields:
  1. `participantKeys` (Array contains)
  2. `updatedAt` (Descending)

The console will usually prompt to create this index automatically on first query.
