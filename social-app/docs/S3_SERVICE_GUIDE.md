# AWS S3 Service - Hướng dẫn sử dụng

## Mục lục
1. [Giới thiệu](#giới-thiệu) (API tóm tắt + bảng file repo)
2. [Cấu hình](#cấu-hình)
3. [Upload file](#upload-file)
4. [Hiển thị file](#hiển-thị-file)
5. [Xóa file](#xóa-file)
6. [Ví dụ thực tế](#ví-dụ-thực-tế)
7. [Troubleshooting](#troubleshooting)

---

## Giới thiệu

### Nguồn chuẩn trong code
- **File thực thi:** `app/services/S3Service.php` — mọi chỗ trong app cần S3 nên dùng class này (upload, xóa, presign, sinh key).
- **Tài liệu này** mô tả *cách làm thống nhất* và ví dụ; nếu lệch với code, ưu tiên `S3Service.php`.

### S3Service là gì?
`S3Service` là class helper để làm việc với AWS S3. Nó cung cấp các method để:
- Upload file lên S3 (`uploadFile`, `uploadStream`)
- Tạo presigned URL (`getPresignedUrl`)
- URL public dạng virtual-hosted (`getPublicUrl` — bucket private thì thường vẫn cần presign để xem)
- Xóa object / cả prefix thư mục (`deleteFile`, `deleteFolder`)
- Sinh key chuẩn: `generateAvatarKey`, `generatePostMediaKey`, `generateChatKey`
- Chuẩn hóa dữ liệu cũ: `extractKeyFromS3Url`, `normalizeStorageToKey` (DB từng lưu full URL thay vì key)

### Bảng API (tóm tắt)

| Method | Ý nghĩa | Giá trị trả về |
|--------|---------|----------------|
| `uploadFile($localPath, $key)` | PUT file từ đĩa | `string` URL public hoặc `false` |
| `uploadStream($stream, $key)` | PUT từ resource | `string` hoặc `false` |
| `getPresignedUrl($key, $seconds)` | GET tạm thời | `string` hoặc `null` |
| `getPublicUrl($key)` | URL cố định (bucket/key) | `string` |
| `deleteFile($keyOrUrl)` | Xóa một object | `bool` |
| `deleteFolder($prefix)` | Xóa theo prefix | `bool` |
| `generatePostMediaKey($postId, $filename)` | Key bài viết | `string` |
| `generateAvatarKey($userId, $filename)` | Key avatar | `string` |
| `generateChatKey($convId, $userId, $filename)` | Key chat | `string` |
| `extractKeyFromS3Url($url)` | static — lấy key từ URL | `?string` |
| `normalizeStorageToKey($stored)` | instance — key hoặc URL → key | `string` |

**Biến môi trường** (đọc lần lượt tên đầu tiên có giá trị):
- Bucket: `AWS_S3_BUCKET`, rồi `AWS_BUCKET`
- Region: `AWS_REGION`, rồi `AWS_DEFAULT_REGION` (mặc định `ap-southeast-1`)
- Credentials: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`  
Đọc qua `env()` nếu có (sau `config/env.php`), không thì `getenv()`.

### Tại sao dùng S3?
- ✅ Không chiếm dung lượng server
- ✅ Presigned URL an toàn (hết hạn sau 24 giờ)
- ✅ Scale tốt khi app grow
- ✅ Dễ backup/restore

### Kho lưu trữ S3
```
Bucket: laravel-deploy-s3
Region: ap-southeast-1 (Singapore)
```

### File trong repo dùng S3Service
| Khu vực | File | Việc chính |
|---------|------|------------|
| Post | `app/controllers/PostController.php` | `store` / `update`: upload media; `update`: xóa media đã chọn trên S3 |
| Post | `app/models/Post.php` | `delete`: xóa toàn bộ object media của bài rồi xóa post |
| User | `app/controllers/UserController.php` | `uploadAvatar`: xóa avatar cũ, upload mới, JSON trả presigned |
| Chat | `app/controllers/MessageController.php` | Upload đính kèm, JSON `storagePath` = key, `url` = presigned |
| View | `app/helpers/media.php` | `media_public_src()` — presign + legacy URL |
| Gợi ý follow | `app/views/partials/feed/right_widgets.php` | Avatar qua `media_public_src` |

---

## Cấu hình

### 1. Cài đặt .env

Tạo file `.env` ở project root với các biến:

```env
# AWS S3 Configuration (khớp với code: AWS_S3_BUCKET + AWS_REGION)
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_REGION=ap-southeast-1
# Tuỳ chọn: AWS_DEFAULT_REGION nếu không dùng AWS_REGION
AWS_S3_BUCKET=your-bucket-name
# Tuỳ chọn: alias Laravel-style
# AWS_BUCKET=your-bucket-name
```

**Lấy credentials:**
1. Vào AWS IAM Console
2. Tạo User có quyền S3
3. Attach policy `AmazonS3FullAccess`
4. Tạo Access Keys
5. Copy vào .env

### 2. Cài đặt AWS SDK

SDK đã được cài qua Composer (`composer.json`):

```json
{
  "require": {
    "aws/aws-sdk-php": "^3.376"
  }
}
```

Nếu chưa cài, chạy:
```bash
composer require aws/aws-sdk-php
```

### 3. Verify cấu hình

**CLI (khuyến nghị):** từ thư mục `social-app` chạy `php test_s3.php` hoặc mở `upload_s3.php` qua browser sau khi cấu hình `.env`.

```php
<?php
require 'vendor/autoload.php';
require 'config/env.php';
require 'app/services/S3Service.php';

$s3 = new S3Service();
echo "OK";
```

---

## Upload file

### Basic Usage

```php
<?php
require 'vendor/autoload.php';
require 'config/env.php';
require 'app/services/S3Service.php';

$s3Service = new S3Service();

// Ví dụ 1: Upload avatar
$userId = 123;
$filename = 'avatar.jpg';
$filePath = '/tmp/uploaded_file.jpg';

$s3Key = $s3Service->generateAvatarKey($userId, $filename);
$s3Url = $s3Service->uploadFile($filePath, $s3Key);

if ($s3Url) {
    // Lưu S3 key vào database (không phải URL)
    $db->query("UPDATE users SET avatar_url = ? WHERE id = ?", [$s3Key, $userId]);
    echo "✅ Upload thành công: " . $s3Key;
} else {
    echo "❌ Upload thất bại";
}
?>
```

### Generate S3 Keys

S3Service cung cấp 3 method để generate key paths:

#### 1. Avatar Key
```php
$s3Key = $s3Service->generateAvatarKey($userId, $filename);
// Result: avatars/123/1704067200_avatar.jpg
```

#### 2. Post Media Key
```php
$s3Key = $s3Service->generatePostMediaKey($postId, $filename);
// Result: posts/456/1704067200_image.jpg
```

#### 3. Chat Attachment Key
```php
$s3Key = $s3Service->generateChatKey($conversationId, $userId, $filename);
// Result: chat/789/123/1704067200_document.pdf
```

### Upload Method

```php
public function uploadFile($filePath, $key): string|false
```

**Tham số:**
- `$filePath`: Đường dẫn file trên server (ví dụ: `$_FILES['x']['tmp_name']`)
- `$key`: S3 key (ví dụ: `avatars/123/1704067200_pic.jpg`)

**Trả về:**
- `string`: `getPublicUrl($key)` nếu upload thành công
- `false`: Lỗi hoặc key rỗng sau khi chuẩn hóa

**Ví dụ:**
```php
$s3Url = $s3Service->uploadFile('/tmp/avatar.jpg', 'avatars/123/avatar.jpg');
```

---

## Hiển thị file

### Dùng Presigned URL

**Presigned URL** là URL tạm thời có thời hạn (mặc định 24 giờ). Browser có thể access mà không cần AWS credentials.

#### Cách 1: Dùng `media_public_src()` helper (`app/helpers/media.php`)

Helper này **đồng bộ với S3Service**: với key `avatars/…`, `posts/…`, `chat/…` sẽ gọi `getPresignedUrl` (cache session 24h). Nếu DB còn **full URL S3** (legacy), sẽ cố trích key bằng `S3Service::extractKeyFromS3Url` rồi presign.

```php
<?php
// Trong view file
$avatarUrl = 'avatars/123/avatar.jpg'; // S3 key từ DB (khuyến nghị)

// Generate presigned URL
$displayUrl = media_public_src($avatarUrl);
?>

<img src="<?= htmlspecialchars($displayUrl) ?>" alt="Avatar">
```

#### Cách 2: Dùng S3Service trực tiếp

```php
<?php
$s3Service = new S3Service();
$s3Key = 'avatars/123/avatar.jpg';
$presignedUrl = $s3Service->getPresignedUrl($s3Key, 86400); // 24 giờ; có thể null nếu lỗi
?>

<img src="<?= htmlspecialchars($presignedUrl ?? '') ?>" alt="Avatar">
```

#### Cách 3: API JSON Response

```php
<?php
// Controller
$post = $postModel->find($postId);
$mediaUrls = [];

foreach ($post['media'] as $media) {
    $mediaUrls[] = [
        's3_key' => $media['media_url'],
        'display_url' => media_public_src($media['media_url'])
    ];
}

header('Content-Type: application/json');
echo json_encode(['media' => $mediaUrls]);
?>
```

### HTML Example

```html
<!-- Avatar với fallback -->
<img 
    src="<?= htmlspecialchars(media_public_src($avatarUrl)) ?>" 
    alt="Avatar"
    width="40" 
    height="40" 
    class="rounded-circle"
    style="object-fit: cover;"
    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
>
<span class="avatar-fallback">H</span>
```

---

## Xóa file

### Basic Usage

`deleteFile` chấp nhận **S3 key** hoặc **full URL** (chuẩn hóa nội bộ giống các method khác).

```php
<?php
$s3Service = new S3Service();
$s3Key = 'avatars/123/avatar.jpg';

if ($s3Service->deleteFile($s3Key)) {
    echo "✅ File deleted";
} else {
    echo "❌ Delete failed";
}
?>
```

**Ví dụ thực tế:**

```php
<?php
// Khi user upload avatar mới, xóa cái cũ
$oldAvatarKey = 'avatars/123/old_avatar.jpg';
$s3Service->deleteFile($oldAvatarKey);

// Upload avatar mới
$newAvatarKey = $s3Service->generateAvatarKey($userId, $filename);
$s3Service->uploadFile($filePath, $newAvatarKey);

// Update DB
$db->query("UPDATE users SET avatar_url = ? WHERE id = ?", [$newAvatarKey, $userId]);
?>
```

---

## Ví dụ thực tế

### 1. Upload Avatar (UserController — khớp app hiện tại)

Luồng chuẩn:
1. Đọc `avatar_url` cũ → nếu khác key mới thì `deleteFile($old)`.
2. `uploadFile` → DB/session lưu **key**; JSON trả về **presigned URL** (24h) để UI hiển thị ngay kể cả bucket private.

```php
$s3Service = new S3Service();
$s3Key = $s3Service->generateAvatarKey($userId, $file['name']);
$s3Url = $s3Service->uploadFile($file['tmp_name'], $s3Key);

if ($s3Url) {
    if ($oldAvatar !== '' && $oldAvatar !== $s3Key) {
        $s3Service->deleteFile($oldAvatar);
    }
    $userModel->updateAvatar($userId, $s3Key);
    $_SESSION['user']['avatar_url'] = $s3Key;
    $displayUrl = $s3Service->getPresignedUrl($s3Key, 86400) ?: $s3Url;
    echo json_encode(['success' => true, 'url' => $displayUrl]);
}
```

### 2. Upload Post Media (PostController — khớp app hiện tại)

**Thứ tự bắt buộc:** tạo bài (`Post::create` lấy `$postId`) **trước**, vì key có dạng `posts/{postId}/…`.

```php
$postId = $postModel->create([...]);
$s3Service = new S3Service();
$mediaModel = new PostMedia();
$mediaUrls = [];

if (!empty($_FILES['media']['name'][0])) {
    foreach ($_FILES['media']['tmp_name'] as $i => $tmpFile) {
        if (!is_uploaded_file($tmpFile)) {
            continue;
        }
        $name = $_FILES['media']['name'][$i];
        $key = $s3Service->generatePostMediaKey($postId, $name);
        if ($s3Service->uploadFile($tmpFile, $key)) {
            $mediaUrls[] = $key;
        }
    }
    foreach ($mediaUrls as $key) {
        $mediaModel->addMedia($postId, $key);
    }
}
```

**Xóa bài:** `Post::delete($id)` trong model đã gọi S3 `deleteFile` cho từng `post_media` rồi xóa DB (đồng bộ với admin xóa bài).

### 3. Hiển thị Post Media trong View

```php
<?php
// post_card.php
$post = $data['post']; // Post object từ controller
$mediaUrls = $post['media'] ?? []; // Array of S3 keys
?>

<div class="post-media">
    <?php foreach ($mediaUrls as $mediaKey): ?>
        <?php
            $displayUrl = media_public_src($mediaKey);
            $ext = pathinfo($mediaKey, PATHINFO_EXTENSION);
        ?>
        
        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
            <!-- Image -->
            <img 
                src="<?= htmlspecialchars($displayUrl) ?>" 
                class="post-image"
                alt="Post media"
            >
        <?php elseif (in_array($ext, ['mp4', 'webm', 'ogg'])): ?>
            <!-- Video -->
            <video class="post-video" controls>
                <source src="<?= htmlspecialchars($displayUrl) ?>" type="video/<?= $ext ?>">
            </video>
        <?php else: ?>
            <!-- Other files -->
            <a href="<?= htmlspecialchars($displayUrl) ?>" class="download-link">
                📄 Download: <?= htmlspecialchars(basename($mediaKey)) ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
```

---

## Troubleshooting

### ❌ "Class 'Aws\S3\S3Client' not found" hoặc feed trắng / ERROR DEBUG

**Nguyên nhân:** `composer.json` thiếu dependency hoặc chưa chạy `composer install` đầy đủ — trong `vendor` chỉ có từng phần AWS mà **không có Guzzle** là autoload sẽ không nạp được `S3Client`.

**Giải pháp** (trong thư mục `social-app`):

```bash
composer update
```

Nếu Composer báo **SSL certificate** (Windows): tải [cacert.pem](https://curl.se/ca/cacert.pem), rồi trong `php.ini` của XAMPP đặt:

`curl.cainfo = "C:\path\to\cacert.pem"` và `openssl.cafile = "C:\path\to\cacert.pem"`, khởi động lại Apache, chạy lại `composer update`.

App đã xử lý **mềm**: thiếu SDK thì feed vẫn chạy (ảnh S3 có thể trống); sau khi cài xong SDK, presign/upload hoạt động lại bình thường.

### ❌ "The AWS Access Key Id does not exist"

**Nguyên nhân:** AWS credentials sai hoặc .env không được load

**Giải pháp:**
1. Check .env file có các key AWS không
2. Kiểm tra credentials trên AWS IAM Console
3. Test bằng:
```php
echo $_ENV['AWS_ACCESS_KEY_ID']; // Phải in ra access key
```

### ❌ "Unable to connect to S3"

**Nguyên nhân:** Security group, network, hoặc region sai

**Giải pháp:**
1. Check EC2 Security Group cho phép outbound HTTPS
2. Check region: `ap-southeast-1` (Singapore)
3. Test kết nối: chạy `upload_s3.php` sau khi cấu hình `.env`, hoặc `uploadFile` một file thử trong code.

### ❌ "Presigned URL returns empty"

**Nguyên nhân:** S3 key format sai hoặc file không tồn tại

**Giải pháp:**
1. Check S3 key format:
   - Avatar: `avatars/{userId}/{filename}`
   - Post: `posts/{postId}/{filename}`
   - Chat: `chat/{conversationId}/{userId}/{filename}`

2. Verify file tồn tại trên S3:
```bash
aws s3 ls s3://laravel-deploy-s3/avatars/123/ --region ap-southeast-1
```

### ❌ File upload nhưng không thấy trên S3

**Nguyên nhân:** Upload không thành công hoặc IAM policy sai

**Giải pháp:**
1. Check IAM policy có `s3:PutObject` không
2. Enable error logging:
```php
$s3Service = new S3Service();
// Check logs/error.log
```

---

## Database Schema

### Lưu trữ S3 keys trong DB

```sql
-- Users table
ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL;

-- Posts table
CREATE TABLE post_media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    media_url VARCHAR(500) NOT NULL, -- S3 key (e.g., posts/123/avatar.jpg)
    media_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- Chat attachments
CREATE TABLE message_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    media_url VARCHAR(500) NOT NULL, -- S3 key
    file_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);
```

---

## Best Practices

### ✅ DO:
- Lưu **S3 key** vào DB, không phải full URL
- Generate presigned URL **tại display time** (`media_public_src` / `getPresignedUrl`), không lưu presigned vào DB
- TTL presigned: **86400s (24h)** trên feed/avatar; API upload tức thì có thể ngắn hơn (ví dụ 1h chat)
- Xóa object cũ trên S3 khi thay avatar / xóa media (app đã làm qua `deleteFile`)
- Validate file type và size trước upload; với form file dùng `is_uploaded_file`

### ❌ DON'T:
- Lưu presigned URL đầy đủ vào DB (nó sẽ hết hạn)
- Upload file lớn trực tiếp (dùng multipart upload)
- Share AWS credentials trong code
- Cho phép upload file bất kỳ loại

---

## Tham khảo thêm

- [AWS S3 Documentation](https://docs.aws.amazon.com/s3/)
- [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/)
- [Presigned URLs](https://docs.aws.amazon.com/AmazonS3/latest/userguide/PresignedUrlUploadObject.html)

---

**Tác giả:** Development Team  
**Cập nhật lần cuối:** April 3, 2026 (đồng bộ `S3Service.php`)
