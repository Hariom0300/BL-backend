# Dongare Fashion Backend API

## 🛍️ Clean E-commerce Backend System

A minimal, efficient backend API for the Dongare Fashion e-commerce platform.

### 🌐 Live API
- **Backend:** https://bl-backend.onrender.com/api
- **Health Check:** https://bl-backend.onrender.com/api/health

### 📋 Features
- ✅ RESTful API design
- ✅ JWT authentication
- ✅ Product management
- ✅ Shopping cart
- ✅ Order processing
- ✅ User management
- ✅ Docker deployment ready

### 🛠️ Tech Stack
- **PHP 8.1+** - Backend language
- **PostgreSQL** - Database
- **JWT** - Authentication
- **Docker** - Containerization
- **Render** - Hosting

### 📁 Project Structure
```
BL-backend/
├── api/
│   └── index.php              # API entry point
├── src/
│   ├── Database.php           # Database connection
│   ├── Auth.php              # Authentication
│   ├── Products.php          # Product management
│   ├── Cart.php              # Shopping cart
│   └── Orders.php            # Order processing
├── Dockerfile                 # Container config
├── render.yaml               # Render deployment
├── .env.example              # Environment variables
├── .htaccess                 # Apache config
└── README.md                 # Documentation
```

### 🚀 Quick Start

1. **Clone repository:**
   ```bash
   git clone https://github.com/Hariom0300/BL-backend.git
   cd BL-backend
   ```

2. **Setup environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

3. **Start development server:**
   ```bash
   php -S localhost:8000 -t api
   ```

### � API Endpoints

#### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration
- `GET /api/auth/me` - Get current user

#### Products
- `GET /api/products` - Get all products
- `GET /api/products/{id}` - Get single product
- `POST /api/products` - Create product (admin)
- `PUT /api/products/{id}` - Update product (admin)
- `DELETE /api/products/{id}` - Delete product (admin)

#### Categories
- `GET /api/categories` - Get all categories

#### Cart
- `GET /api/cart` - Get cart items
- `POST /api/cart` - Add item to cart
- `PUT /api/cart` - Update cart item
- `DELETE /api/cart` - Remove cart item

#### Orders
- `GET /api/orders` - Get user orders
- `GET /api/orders/{id}` - Get single order
- `POST /api/orders` - Create order
- `PUT /api/orders/{id}` - Update order status
- `DELETE /api/orders/{id}` - Cancel order

### 🔧 Configuration

#### Environment Variables
```bash
DB_HOST=localhost
DB_NAME=dongare_ecommerce
DB_USER=postgres
DB_PASS=your_password
JWT_SECRET=your_jwt_secret
FRONTEND_URL=http://localhost:3000
ENVIRONMENT=development
```

### 🚀 Deployment

#### Render (Recommended)
1. Connect repository to Render
2. Use Docker configuration
3. Set environment variables
4. Deploy automatically

### 📞 Support

- **Email:** hariomvimal33333@gmail.com
- **Phone:** +91 98341 34470

---

**🛍️ Dongare Fashion - Minimal E-commerce Backend**
