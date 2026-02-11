# Debugging Product Creation Issue

## The Problem
Product creation fails with "Database insert operation failed"

## Likely Causes

### 1. **Session Not Maintained in Postman**

When you register/login, the session is created. But if you're making separate requests in Postman, the session might not be carried over.

**Solution: Check if you're authenticated**

Before creating a product, call:
```
GET http://localhost/wrightcommerce/public/auth/check
```

**If you get:**
```json
{
  "authenticated": false
}
```

Then you need to login again in the SAME Postman tab/session.

---

## How to Fix in Postman

### **Option 1: Login First, Then Create Product in Same Session**

**Step 1: Login**
- Method: `POST`
- URL: `http://localhost/wrightcommerce/public/auth/login`
- Body:
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Step 2: Immediately Create Product (Same Tab)**
- Method: `POST`
- URL: `http://localhost/wrightcommerce/public/api/v1/products`
- Body:
```json
{
  "name": "iPhone 15 Pro",
  "price": 120000,
  "stock": 10
}
```

---

### **Option 2: Check Session Management**

Add this endpoint to debug what's in the session:

**Create a debug endpoint in index.php:**

```php
// Add this BEFORE $router->dispatch()
$router->get('/debug/session', function() {
    session_start();
    echo json_encode([
        'session_data' => $_SESSION,
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'business_id' => $_SESSION['business_id'] ?? 'NOT SET'
    ], JSON_PRETTY_PRINT);
});
```

**Then call:**
```
GET http://localhost/wrightcommerce/public/debug/session
```

**You should see:**
```json
{
  "session_data": {
    "user_id": 1,
    "business_id": 1,
    "user_email": "john@example.com",
    "user_name": "John Kamau",
    "user_role": "owner"
  },
  "user_id": 1,
  "business_id": 1
}
```

**If you see:**
```json
{
  "session_data": {},
  "user_id": "NOT SET",
  "business_id": "NOT SET"
}
```

Then the session is not being maintained.

---

## Quick Fix: Add business_id Manually (Temporary)

For testing, you can temporarily add business_id to the product creation request:

```json
{
  "name": "iPhone 15 Pro",
  "description": "Latest Apple smartphone",
  "price": 120000,
  "cost_price": 100000,
  "stock": 10,
  "sku": "IP15-PRO-128",
  "category": "Electronics",
  "business_id": 1
}
```

But this is NOT secure for production! Only use for testing.

---

## Proper Solution: Enable Better Session Debugging

Let me create an improved version of the ProductController store() method that shows exactly what's failing.

## Findings:
Even when business_id is not null, or even when all expected values are not null, we still get these lines in the logs:
```
[2026-02-11 13:22:57] [info] Attempting to create product with data: {"business_id":1,"name":"iPhone 15 Pro","description":"","price":120000,"cost_price":0,"stock":10,"sku":null,"category":"","status":"active"}
[2026-02-11 13:22:57] [error] Failed to create product: Database insert operation failed
[2026-02-11 13:25:28] [info] Attempting to create product with data: {"business_id":1,"name":"iPhone 15 Pro","description":"Latest Apple smartphone","price":120000,"cost_price":100000,"stock":10,"sku":"IP15-PRO-128","category":"Electronics","status":"active"}
[2026-02-11 13:25:28] [error] Failed to create product: Database insert operation failed
```