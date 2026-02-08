# WrightCommerce API - Testing Guide

This guide shows you how to test your Product API endpoints using cURL, Postman, or any HTTP client.

**Base URL:** `http://localhost/wrightcommerce/public`

---

## 🔧 Setup First

Before testing, make sure:
1. ✅ XAMPP is running (Apache + MySQL)
2. ✅ All files are in place:
   - `C:\xampp\htdocs\wrightcommerce\public\index.php`
   - `C:\xampp\htdocs\wrightcommerce\app\controllers\ProductController.php`
   - `C:\xampp\htdocs\wrightcommerce\app\controllers\BaseController.php`
   - `C:\xampp\htdocs\wrightcommerce\app\helpers\Router.php`
   - `C:\xampp\htdocs\wrightcommerce\app\helpers\Database.php`
   - `C:\xampp\htdocs\wrightcommerce\config\database.php`

3. ✅ Database tables exist (run the SQL from Week 1)

---

## 📋 Quick Tests

### 1. Test API is Online
```bash
curl http://localhost/wrightcommerce/public/
```

**Expected Response:**
```json
{
  "app": "WrightCommerce API",
  "version": "1.0",
  "status": "online",
  "timestamp": "2025-02-08 10:30:45"
}
```

### 2. Test Database Connection
```bash
curl http://localhost/wrightcommerce/public/test-db
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Database connection successful",
  "database": "wrightcommerce"
}
```

---

## 📦 Product Endpoints Testing

### 1. CREATE PRODUCT (POST)

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "iPhone 15 Pro",
    "description": "Latest Apple smartphone with A17 Pro chip",
    "price": 120000,
    "cost_price": 100000,
    "stock": 15,
    "sku": "IP15-PRO-128",
    "category": "Electronics"
  }'
```

**Postman:**
- Method: `POST`
- URL: `http://localhost/wrightcommerce/public/api/v1/products`
- Headers: `Content-Type: application/json`
- Body (raw JSON):
```json
{
  "name": "iPhone 15 Pro",
  "description": "Latest Apple smartphone with A17 Pro chip",
  "price": 120000,
  "cost_price": 100000,
  "stock": 15,
  "sku": "IP15-PRO-128",
  "category": "Electronics"
}
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
    "stock": 15,
    "created_at": "2025-02-08 10:35:00"
  }
}
```

---

### 2. CREATE PRODUCT WITH IMAGE UPLOAD

**Using Postman:**
- Method: `POST`
- URL: `http://localhost/wrightcommerce/public/api/v1/products`
- Body: Select `form-data`
- Add fields:
  - `name`: iPhone 15 Pro (text)
  - `price`: 120000 (text)
  - `stock`: 15 (text)
  - `image`: [Select file] (file)

**Using cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/api/v1/products \
  -F "name=iPhone 15 Pro" \
  -F "price=120000" \
  -F "stock=15" \
  -F "image=@/path/to/image.jpg"
```

---

### 3. LIST ALL PRODUCTS (GET)

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/api/v1/products
```

**With filters:**
```bash
# Filter by status
curl http://localhost/wrightcommerce/public/api/v1/products?status=active

# Search products
curl http://localhost/wrightcommerce/public/api/v1/products?search=iPhone

# Price range
curl http://localhost/wrightcommerce/public/api/v1/products?min_price=50000&max_price=150000

# Pagination
curl http://localhost/wrightcommerce/public/api/v1/products?page=1&per_page=10

# Low stock items
curl http://localhost/wrightcommerce/public/api/v1/products?low_stock=true
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "iPhone 15 Pro",
        "price": 120000,
        "stock": 15,
        "status": "active"
      }
    ],
    "pagination": {
      "total": 25,
      "per_page": 20,
      "current_page": 1,
      "last_page": 2,
      "from": 1,
      "to": 20
    }
  }
}
```

---

### 4. GET SINGLE PRODUCT (GET)

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/api/v1/products/1
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Product retrieved successfully",
  "data": {
    "id": 1,
    "business_id": 1,
    "name": "iPhone 15 Pro",
    "description": "Latest Apple smartphone",
    "price": 120000,
    "cost_price": 100000,
    "stock": 15,
    "sku": "IP15-PRO-128",
    "category": "Electronics",
    "status": "active",
    "created_at": "2025-02-08 10:35:00"
  }
}
```

---

### 5. UPDATE PRODUCT (PUT)

**cURL:**
```bash
curl -X PUT http://localhost/wrightcommerce/public/api/v1/products/1 \
  -H "Content-Type: application/json" \
  -d '{
    "price": 125000,
    "stock": 12,
    "description": "Updated description"
  }'
```

**Postman:**
- Method: `PUT`
- URL: `http://localhost/wrightcommerce/public/api/v1/products/1`
- Headers: `Content-Type: application/json`
- Body (raw JSON):
```json
{
  "price": 125000,
  "stock": 12,
  "description": "Updated description"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Product updated successfully",
  "data": {
    "id": 1,
    "price": 125000,
    "stock": 12,
    "updated_at": "2025-02-08 11:00:00"
  }
}
```

---

### 6. DELETE PRODUCT (Soft Delete)

**cURL:**
```bash
curl -X DELETE http://localhost/wrightcommerce/public/api/v1/products/1
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Product deleted successfully"
}
```

---

### 7. SEARCH PRODUCTS

**cURL:**
```bash
curl "http://localhost/wrightcommerce/public/api/v1/products/search?q=iPhone&limit=5"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Found 3 products",
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "price": 120000
    },
    {
      "id": 2,
      "name": "iPhone 15",
      "price": 110000
    }
  ]
}
```

---

### 8. LOW STOCK PRODUCTS

**cURL:**
```bash
curl "http://localhost/wrightcommerce/public/api/v1/products/low-stock?threshold=10"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Found 5 low stock items",
  "data": {
    "products": [...],
    "count": 5,
    "threshold": 10
  }
}
```

---

### 9. ADJUST STOCK

**cURL:**
```bash
# Add 10 items to stock
curl -X POST http://localhost/wrightcommerce/public/api/v1/products/1/adjust-stock \
  -H "Content-Type: application/json" \
  -d '{
    "adjustment": 10,
    "reason": "New stock received"
  }'

# Subtract 5 items from stock
curl -X POST http://localhost/wrightcommerce/public/api/v1/products/1/adjust-stock \
  -H "Content-Type: application/json" \
  -d '{
    "adjustment": -5,
    "reason": "Damaged items removed"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Stock adjusted successfully",
  "data": {
    "old_stock": 15,
    "new_stock": 25,
    "adjustment": 10
  }
}
```

---

### 10. GET PRODUCT STATISTICS

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/api/v1/products/stats
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Product statistics retrieved successfully",
  "data": {
    "total_products": 45,
    "out_of_stock": 5,
    "low_stock": 8,
    "inactive_products": 3,
    "total_inventory_value": 5400000,
    "total_inventory_cost": 4200000,
    "potential_profit": 1200000
  }
}
```

---

### 11. GET CATEGORIES

**cURL:**
```bash
curl http://localhost/wrightcommerce/public/api/v1/products/categories
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Categories retrieved successfully",
  "data": [
    "Electronics",
    "Clothing",
    "Food & Beverages",
    "Home & Garden"
  ]
}
```

---

### 12. BULK UPDATE STATUS

**cURL:**
```bash
curl -X POST http://localhost/wrightcommerce/public/api/v1/products/bulk-update-status \
  -H "Content-Type: application/json" \
  -d '{
    "product_ids": [1, 2, 3, 4, 5],
    "status": "inactive"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "5 products updated successfully",
  "data": {
    "updated_count": 5,
    "status": "inactive"
  }
}
```

---

## 🚨 Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["Name is required"],
    "price": ["Price must be a number"]
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Product not found"
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Authentication required"
}
```

---

## 📝 Sample Test Data

Use this JSON to quickly create test products:

```json
[
  {
    "name": "Samsung Galaxy S24",
    "description": "Flagship Android smartphone",
    "price": 85000,
    "cost_price": 70000,
    "stock": 20,
    "sku": "SAM-S24-256",
    "category": "Electronics"
  },
  {
    "name": "Nike Air Max",
    "description": "Premium running shoes",
    "price": 12000,
    "cost_price": 8000,
    "stock": 50,
    "sku": "NIKE-AM-42",
    "category": "Footwear"
  },
  {
    "name": "Dell XPS 15 Laptop",
    "description": "High-performance laptop for professionals",
    "price": 180000,
    "cost_price": 150000,
    "stock": 8,
    "sku": "DELL-XPS15-512",
    "category": "Electronics"
  },
  {
    "name": "Coffee Beans 1kg",
    "description": "Premium Arabica coffee beans from Kenya",
    "price": 1500,
    "cost_price": 1000,
    "stock": 100,
    "sku": "COFFEE-1KG",
    "category": "Food & Beverages"
  },
  {
    "name": "Office Chair Ergonomic",
    "description": "Comfortable office chair with lumbar support",
    "price": 15000,
    "cost_price": 12000,
    "stock": 15,
    "sku": "CHAIR-ERG-01",
    "category": "Furniture"
  }
]
```

---

## 🔄 Testing Workflow

1. **Create products** using POST
2. **List products** using GET to verify they were created
3. **Update a product** using PUT
4. **Search for products** using the search endpoint
5. **Adjust stock** using the stock adjustment endpoint
6. **Check stats** to see your inventory summary
7. **Soft delete** a product
8. **Test filters** (low stock, price range, search, etc.)

---

## 💡 Tips

- Use **Postman Collections** to save all your requests
- Use **Postman Environment Variables** for the base URL
- Check the **logs/** folder for error logs
- Look at **Apache error logs** in XAMPP if you get 500 errors
- Test with **invalid data** to verify validation works
- Test **authentication** once you implement AuthController

---

## 🛠️ Postman Collection

Save this as a Postman Collection for quick testing:

1. Open Postman
2. Click "Import"
3. Create a new Collection called "WrightCommerce API"
4. Add each endpoint as a request
5. Set base URL variable: `{{base_url}}` = `http://localhost/wrightcommerce/public`

---

**Next:** Once you've tested the Product API, you'll move on to creating AuthController (Week 1) and OrderController (Week 3)!