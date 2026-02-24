# Hướng dẫn tích hợp Mem0 vào n8n

## 📚 Các bước cấu hình

### 1. Đăng ký & Lấy API Key từ Mem0

1. Truy cập: https://app.mem0.ai
2. Đăng ký tài khoản (hỗ trợ free tier)
3. Tạo một Project
4. Vào **Settings** → **API Keys**
5. Copy **API Key** (ví dụ: `mem0_xxx...`)

### 2. Cấu hình API Key trong PHP

**File**: `d:\vscode\Scrum\api\chatbot-with-memory.php`

Thay dòng:
```php
define('MEM0_API_KEY', 'your-mem0-api-key-here');
```

Thành:
```php
define('MEM0_API_KEY', 'mem0_your_actual_api_key_here');
```

### 3. Cấu hình n8n Workflow

N8n workflow của bạn cần:

**Input** (từ PHP):
```json
{
  "message": "user message",
  "userContext": "previous memories nếu có",
  "userId": "user_id",
  "timestamp": "2026-02-24T..."
}
```

**Output** (trả về PHP):
```json
{
  "output": "bot response",
  "success": true
}
```

### 4. Mem0 làm gì?

#### **Lần đầu tiên:**
```
User: "Tôi là Khoa, làm việc tại Công ty ABC"
    ↓
Mem0: Lưu memory "User name is Khoa, works at ABC Corp"
    ↓
Bot: "Xin chào Khoa!"
    ↓
Conversation lưu vào Mem0
```

#### **Lần thứ 2:**
```
User: "Bạn nhớ tên tôi không?"
    ↓
Mem0: Search memories → Tìm thấy "User name is Khoa"
    ↓
Context gửi đến n8n: "User Background: User name is Khoa..."
    ↓
Bot: "Tên bạn là Khoa!"
    ↓
Memory được cập nhật
```

---

## 🔧 API Endpoints có sẵn

### Mem0Manager Class

```php
$mem0 = new Mem0Manager($apiKey, $userId);

// Thêm memory
$mem0->addMemory("User is interested in AI");

// Tìm kiếm memories
$results = $mem0->searchMemories("user interest");

// Lấy context cho LLM
$context = $mem0->getContextForLLM("ai");

// Lấy tất cả memories
$memories = $mem0->getMemories();

// Cập nhật memory
$mem0->updateMemory($memoryId, "Updated info");

// Xóa memory
$mem0->deleteMemory($memoryId);
```

---

## 📊 Dữ liệu lưu trong Mem0

Mỗi conversation được lưu:
```json
{
  "memory": "User: message content\nBot: response",
  "type": "conversation",
  "timestamp": "2026-02-24T10:30:00Z",
  "user_id": "user_123"
}
```

---

## ✅ Kiểm tra hoạt động

1. **Mở browser**: `http://localhost:8000/index.php`
2. **Đăng nhập với Face ID**
3. **Chat** và xem memories được lưu
4. **Kiểm tra Mem0 Dashboard**: https://app.mem0.ai → Project → Memories

---

## 🐛 Debug

Nếu có lỗi, check:

1. **MEM0_API_KEY**: Đã set đúng chưa?
2. **Network**: Kiểm tra Console (F12) → Network → chatbot-with-memory.php
3. **Mem0 Dashboard**: Xem memories có được lưu không?
4. **n8n Webhook**: Còn hoạt động không?

---

## 🚀 Nâng cao

### Ghi nhớ user preferences
```php
$mem0->addMemory(
    "User prefers Vietnamese language, likes AI topics",
    ['type' => 'preferences']
);
```

### Phân loại memories
```php
$context = $mem0->getContextForLLM("how to use AI");
// Chỉ lấy memories liên quan đến "AI"
```

### Cập nhật memories từ interaction
```php
// Nếu user nói điều gì mới
if (contains($message, 'I work at')) {
    $mem0->addMemory($message, ['type' => 'work_info']);
}
```

---

## 📝 Ghi chú

- **Mem0 Free tier**: Hỗ trợ số lượng memories hạn chế
- **Performance**: Mem0 search có latency ~1-2 giây
- **Accuracy**: Depends on n8n configuration
- **Privacy**: Tất cả data lưu trên server của Mem0

---

Bạn đã tích hợp thành công Mem0 + n8n! 🎉
