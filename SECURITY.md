# 🔒 Security Guide - Bảo mật Dự án

## 📋 Quy tắc bảo mật

### ❌ KHÔNG được commit những file này:
```
.env                 # Chứa secrets thật
.env.local
config/secrets.php
vendor/
node_modules/
*.log
```

### ✅ CÓ thể commit:
```
.env.example         # Template, không có secrets
.gitignore
README.md
public/
src/
```

---

## 🚀 Setup lần đầu cho nhân viên mới

### 1. Clone repository
```bash
git clone https://github.com/DK0310/Scrum_App.git
cd Scrum_App
```

### 2. Copy `.env.example` thành `.env`
```bash
cp .env.example .env
```

### 3. Điền thông tin thực vào `.env`
```bash
# Edit .env file với text editor
# Thêm các giá trị thực:
DB_PASSWORD=your_actual_password
MEM0_API_KEY=your_actual_api_key
N8N_WEBHOOK_URL=your_actual_webhook
```

### 4. Set permissions (Linux/Mac)
```bash
chmod 600 .env
```

---

## 🔐 Các loại secrets cần bảo mật

| Secret | Nơi lưu | Cách lấy |
|--------|---------|---------|
| DB Password | `.env` | Supabase Dashboard |
| DB Username | `.env` | Supabase Dashboard |
| API Keys | `.env` | Mem0/n8n Dashboard |
| Webhook URLs | `.env` | n8n Workflow |
| Session Keys | `.env` | Tự sinh |

---

## 🛡️ Best Practices

### 1. **Không hardcode secrets trong code**
```php
// ❌ SAI
$password = "Khangkhang0310@";

// ✅ ĐÚNG
$password = EnvLoader::get('DB_PASSWORD');
```

### 2. **Use `.env.example` cho template**
```bash
# .env.example (commit)
DB_PASSWORD=your_password_here

# .env (ignore, local only)
DB_PASSWORD=Khangkhang0310@
```

### 3. **Review `.gitignore` trước khi commit**
```bash
# Kiểm tra file sẽ commit
git status

# Nếu thấy .env hoặc secrets → STOP!
git rm --cached .env  # Xóa khỏi git
```

### 4. **Use environment variables cho configuration**
```php
// Config từ .env thông qua EnvLoader
$apiKey = EnvLoader::get('MEM0_API_KEY');
$dbHost = EnvLoader::get('DB_HOST');
```

---

## 🚨 Nếu vô tình commit secrets

### 1. Xóa file khỏi git history
```bash
git rm --cached .env
git commit -m "Remove .env from tracking"
git push
```

### 2. Thay đổi credentials ngay lập tức!
```
- Supabase: Đổi password
- Mem0: Regenerate API key
- n8n: Reset webhooks
```

### 3. Add `.env` vào `.gitignore`
```bash
echo ".env" >> .gitignore
git add .gitignore
git commit -m "Add .env to gitignore"
git push
```

---

## 🔄 GitHub Secrets (cho CI/CD)

Nếu dùng GitHub Actions:

```yaml
# .github/workflows/deploy.yml
name: Deploy

on: [push]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Create .env
        run: |
          echo "DB_PASSWORD=${{ secrets.DB_PASSWORD }}" > .env
          echo "MEM0_API_KEY=${{ secrets.MEM0_API_KEY }}" >> .env
```

### Set secrets trong GitHub:
1. Vào **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret**
3. Thêm từng secret:
   - `DB_PASSWORD`
   - `MEM0_API_KEY`
   - `N8N_WEBHOOK_URL`

---

## ✅ Checklist trước khi push

- [ ] Không có `.env` file trong commit
- [ ] Kiểm tra `git log` không có secrets
- [ ] `.env` trong `.gitignore`
- [ ] Khác nhân viên có `.env.example`?
- [ ] Test lại app chạy bình thường

---

## 📚 Tài liệu tham khảo

- [12factor.net - Config](https://12factor.net/config)
- [GitHub - Removing sensitive data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [OWASP - Secrets Management](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html)

---

**Ghi nhớ**: Security là trách nhiệm của tất cả! 🛡️
