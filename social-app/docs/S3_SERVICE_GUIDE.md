# AWS S3 Service - Hướng dẫn sử dụng

## Mục lục
1. [Giới thiệu](#giới-thiệu)
2. [Cấu hình](#cấu-hình)
3. [Upload file](#upload-file)
4. [Hiển thị file](#hiển-thị-file)
5. [Xóa file](#xóa-file)
6. [Ví dụ thực tế](#ví-dụ-thực-tế)
7. [Troubleshooting](#troubleshooting)

---

## Giới thiệu

### S3Service là gì?
`S3Service` là class helper để làm việc với AWS S3. Nó cung cấp các method để:
- Upload file lên S3
- Tạo presigned URL (URL tạm thời có thời hạn)
- Xóa file khỏi S3
- Generate S3 key path

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

---

## Cấu hình

### 1. Cài đặt .env

Tạo file `.env` ở project root với các biến:

```env
# AWS S3 Configuration
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=laravel-deploy-s3
AWS_URL=https://laravel-deploy-s3.s3.ap-southeast-1.amazonaws.com
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

```php
<?php
require 'vendor/autoload.php';
require 'config/env.php';
require 'app/services/S3Service.php';

try {
    $s3 = new S3Service();
    echo "✅ S3 Service connected successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
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
$s3Key = $s3Service->generatePostKey($postId, $filename);
// Result: posts/456/1704067200_image.jpg
```

#### 3. Chat Attachment Key
```php
$s3Key = $s3Service->generateChatKey($conversationId, $userId, $filename);
// Result: chat/789/123/1704067200_document.pdf
```

### Upload Method

```php
public function uploadFile(string $localPath, string $s3Key): ?string
```

**Tham số:**
- `$localPath`: Đường dẫn file trên server (ví dụ: `/tmp/upload_xyz`)
- `$s3Key`: S3 key path (ví dụ: `avatars/123/avatar.jpg`)

**Trả về:**
- `string` (URL): Nếu upload thành công
- `null`: Nếu upload thất bại

**Ví dụ:**
```php
$s3Url = $s3Service->uploadFile('/tmp/avatar.jpg', 'avatars/123/avatar.jpg');
```

---

## Hiển thị file

### Dùng Presigned URL

**Presigned URL** là URL tạm thời có thời hạn (mặc định 24 giờ). Browser có thể access mà không cần AWS credentials.

#### Cách 1: Dùng `media_public_src()` helper

```php
<?php
// Trong view file
$avatarUrl = 'avatars/123/avatar.jpg'; // S3 key từ DB

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
$presignedUrl = $s3Service->getPresignedUrl($s3Key, 86400); // 24 giờ
?>

<img src="<?= htmlspecialchars($presignedUrl) ?>" alt="Avatar">
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

### 1. Upload Avatar (UserController)

```php
<?php
class UserController extends BaseController {
    public function uploadAvatar(): void {
        $this->requireAuth();
        
        if (!isset($_FILES['avatar'])) {
            echo json_encode(['error' => 'No file']);
            return;
        }
        
        $file = $_FILES['avatar'];
        
        // Validate
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['error' => 'Invalid file type']);
            return;
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB
            echo json_encode(['error' => 'File too large']);
            return;
        }
        
        $userId = $_SESSION['user']['id'];
        
        try {
            $s3Service = new S3Service();
            $s3Key = $s3Service->generateAvatarKey($userId, $file['name']);
            $s3Url = $s3Service->uploadFile($file['tmp_name'], $s3Key);
            
            if ($s3Url) {
                // Lưu S3 key vào DB
                $userModel = new User();
                $userModel->updateAvatar($userId, $s3Key);
                
                // Update session
                $_SESSION['user']['avatar_url'] = $s3Key;
                
                echo json_encode([
                    'success' => true,
                    'url' => $s3Url,
                    'message' => 'Avatar uploaded'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
?>
```

### 2. Upload Post Media (PostController)

```php
<?php
class PostController extends BaseController {
    public function store(): void {
        $this->requireAuth();
        
        $content = $_POST['content'] ?? '';
        $userId = $_SESSION['user']['id'];
        
        $postId = null; // Tạo post trước, lấy ID
        $mediaUrls = [];
        
        // Upload media files
        if (!empty($_FILES['media'])) {
            $s3Service = new S3Service();
            
            foreach ($_FILES['media']['tmp_name'] as $index => $tmpFile) {
                $originalName = $_FILES['media']['name'][$index];
                $s3Key = $s3Service->generatePostKey($postId, $originalName);
                $s3Url = $s3Service->uploadFile($tmpFile, $s3Key);
                
                if ($s3Url) {
                    $mediaUrls[] = $s3Key; // Lưu S3 key, không phải URL
                }
            }
        }
        
        // Lưu post và media vào DB
        $postModel = new Post();
        $postModel->create($userId, $content, $mediaUrls);
        
        $this->redirect('/');
    }
}
?>
```

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

### ❌ "Class 'Aws\S3\S3Client' not found"

**Nguyên nhân:** AWS SDK chưa được cài hoặc autoload không hoạt động

**Giải pháp:**
```bash
composer install
composer dump-autoload
```

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
3. Test kết nối:
```php
$s3Service = new S3Service();
$client = $s3Service->getClient(); // Có method này không?
```

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
- Generate presigned URL **tại display time**, không lúc upload
- Set presigned URL expiration **24 giờ** (86400 seconds)
- Delete old file trước upload file mới
- Validate file type và size trước upload

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
**Cập nhật lần cuối:** April 2, 2026