# Authentication API - Testing Guide

Complete guide to testing all authentication endpoints in WrightCommerce.

**Base URL:** `http://localhost/wrightcommerce/public`

---

## 📋 **Setup First**

### 1. Run Database Migration

Add password reset fields to users table:

```sql
-- Run this in phpMyAdmin or MySQL command line

ALTER TABLE `users` 
ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `password`,
ADD COLUMN `reset_token_expiry` DATETIME NULL DEFAULT NULL AFTER `reset_token`,
ADD COLUMN `phone` VARCHAR(20) NULL DEFAULT NULL AFTER `email`;

ALTER TABLE `users` ADD INDEX `idx_reset_token` (`reset_token`);

ALTER TABLE `businesses` 
ADD COLUMN `phone` VARCHAR(20) NULL DEFAULT NULL AFTER `email`;
```

### 2. Place Files

```
C:\xampp\htdocs\wrightcommerce\app\controllers\AuthController.php
C:\xampp\htdocs\wrightcommerce\public\index.php (updated)
```

---

## 🧪 **Authentication Flow Testing**

### **Step 1: Register a New User**

**Endpoint:** `POST /auth/register`

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Kamau",
    "email": "john@example.com",
    "password": "password123",
    "business_name": "Kamau Electronics",
    "phone": "0712345678",
    "business_phone": "0723456789"
  }'
```

**Postman:**
- Method: `POST`
- URL: `http://localhost/wrightcommerce/public/auth/register`
- Headers: `Content-Type: application/json`
- Body (raw JSON):
```json
{
  "name": "John Kamau",
  "email": "john@example.com",
  "password": "password123",
  "business_name": "Kamau Electronics",
  "phone": "0712345678",
  "business_phone": "0723456789"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Registration successful! Welcome to WrightCommerce!",
  "data": {
    "user": {
      "id": 1,
      "business_id": 1,
      "name": "John Kamau",
      "email": "john@example.com",
      "role": "owner",
      "created_at": "2025-02-09 18:00:00"
    },
    "business": {
      "id": 1,
      "name": "Kamau Electronics",
      "email": "john@example.com",
      "phone": "254723456789"
    },
    "session": {
      "user_id": 1,
      "business_id": 1
    }
  }
}
```

**Note:** Registration automatically logs you in!

---

### **Step 2: Check Authentication Status**

**Endpoint:** `GET /auth/check`

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/auth/check
```

**Expected Response (Logged In):**
```json
{
  "success": true,
  "message": "User is authenticated",
  "data": {
    "authenticated": true,
    "user_id": 1,
    "business_id": 1
  }
}
```

---

### **Step 3: Get Current User Info**

**Endpoint:** `GET /auth/me`

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/auth/me
```

**Expected Response:**
```json
{
  "success": true,
  "message": "User data retrieved successfully",
  "data": {
    "user": {
      "id": 1,
      "business_id": 1,
      "name": "John Kamau",
      "email": "john@example.com",
      "phone": "254712345678",
      "role": "owner"
    },
    "business": {
      "id": 1,
      "name": "Kamau Electronics",
      "email": "john@example.com",
      "phone": "254723456789"
    }
  }
}
```

---

### **Step 4: Test Product Access (Now Authenticated!)**

**Endpoint:** `GET /api/v1/products`

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/api/v1/products
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "items": [],
    "pagination": {
      "total": 0,
      "per_page": 20,
      "current_page": 1,
      "last_page": 1
    }
  }
}
```

**Note:** No more "Authentication required" error! 🎉

---

### **Step 5: Create a Product**

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "iPhone 15 Pro",
    "description": "Latest Apple smartphone",
    "price": 120000,
    "cost_price": 100000,
    "stock": 10,
    "sku": "IP15-PRO-128",
    "category": "Electronics"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Product created successfully",
  "data": {
    "id": 1,
    "business_id": 1,
    "name": "iPhone 15 Pro",
    "price": 120000,
    "stock": 10
  }
}
```

---

### **Step 6: Logout**

**Endpoint:** `POST /auth/logout`

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/auth/logout
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

---

### **Step 7: Try Accessing Products (Should Fail Now)**

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/api/v1/products
```

**Expected Response:**
```json
{
  "success": false,
  "message": "Authentication required"
}
```

---

### **Step 8: Login Again**

**Endpoint:** `POST /auth/login`

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Login successful! Welcome back!",
  "data": {
    "user": {
      "id": 1,
      "name": "John Kamau",
      "email": "john@example.com",
      "role": "owner"
    },
    "business": {
      "id": 1,
      "name": "Kamau Electronics"
    },
    "session": {
      "user_id": 1,
      "business_id": 1
    }
  }
}
```

---

## 🔐 **Additional Authentication Features**

### **Update Profile**

**Endpoint:** `PUT /auth/profile`

**cURL:**
```bash
curl -X PUT http://localhost/wrightcommerce/public/auth/profile \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John K. Kamau",
    "phone": "0798765432"
  }'
```

---

### **Change Password**

**Endpoint:** `POST /auth/change-password`

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/auth/change-password \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "password123",
    "new_password": "newpassword456",
    "confirm_password": "newpassword456"
  }'
```

---

### **Forgot Password (Request Reset)**

**Endpoint:** `POST /auth/forgot-password`

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com"
  }'
```

**Response (Development Only - includes token):**
```json
{
  "success": true,
  "message": "If the email exists, a password reset link has been sent.",
  "data": {
    "message": "Password reset token generated",
    "token": "abc123def456...",
    "expires_at": "2025-02-09 19:00:00"
  }
}
```

---

### **Reset Password (Using Token)**

**Endpoint:** `POST /auth/reset-password`

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "token": "YOUR_RESET_TOKEN_HERE",
    "new_password": "mynewpassword",
    "confirm_password": "mynewpassword"
  }'
```

---

## 🚨 **Error Responses**

### **Invalid Credentials (Login)**
```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

### **Email Already Exists (Register)**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email already exists"]
  }
}
```

### **Weak Password**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password": ["Password must be at least 6 characters"]
  }
}
```

---

## 🔄 **Complete Test Workflow**

```bash
# 1. Register
curl -X POST http://localhost/wrightcommerce/public/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@test.com","password":"test123","business_name":"Test Business"}'

# 2. Check auth status
curl http://localhost/wrightcommerce/public/auth/check

# 3. Get user info
curl http://localhost/wrightcommerce/public/auth/me

# 4. Create product (should work now!)
curl -X POST http://localhost/wrightcommerce/public/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Product","price":1000,"stock":5}'

# 5. List products
curl http://localhost/wrightcommerce/public/api/v1/products

# 6. Logout
curl -X POST http://localhost/wrightcommerce/public/auth/logout

# 7. Try products again (should fail)
curl http://localhost/wrightcommerce/public/api/v1/products

# 8. Login
curl -X POST http://localhost/wrightcommerce/public/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test123"}'

# 9. Products work again!
curl http://localhost/wrightcommerce/public/api/v1/products
```

---

## 📝 **Notes**

### **Sessions in cURL**

cURL doesn't maintain sessions between requests. For proper testing with sessions:

**Option 1: Use Postman** (maintains cookies/sessions automatically)

**Option 2: Save cookies in cURL:**
```bash
# Register and save session
curl -X POST http://localhost/wrightcommerce/public/auth/register \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"name":"Test","email":"test@test.com","password":"test123","business_name":"Test"}'

# Use saved session for products
curl http://localhost/wrightcommerce/public/api/v1/products \
  -b cookies.txt
```

### **Production Notes**

In production:
1. Remove the token from `forgot-password` response
2. Send actual password reset emails
3. Use HTTPS
4. Set secure session cookies
5. Add rate limiting for login attempts

---

**Your authentication system is now complete!** 🎉