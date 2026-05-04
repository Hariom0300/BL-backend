-- Dongare Fashion E-commerce Database Schema
-- PostgreSQL Database Setup

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INTEGER REFERENCES categories(id),
    image VARCHAR(255),
    stock INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(20) DEFAULT 'customer',
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

-- Cart items table
CREATE TABLE IF NOT EXISTS cart_items (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    product_id INTEGER REFERENCES products(id),
    quantity INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    order_number VARCHAR(50) UNIQUE NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    shipping_address JSONB,
    payment_method VARCHAR(50),
    payment_status VARCHAR(20) DEFAULT 'pending',
    transaction_id VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id),
    product_id INTEGER REFERENCES products(id),
    quantity INTEGER NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product variants table (for sizes, colors, etc.)
CREATE TABLE IF NOT EXISTS product_variants (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id),
    variant_type VARCHAR(50), -- 'size', 'color', etc.
    variant_value VARCHAR(100), -- 'M', 'L', 'XL', 'Red', 'Blue', etc.
    price_adjustment DECIMAL(10,2) DEFAULT 0,
    stock_adjustment INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Shirts', 'Formal and casual shirts for all occasions'),
('Jeans', 'Denim jeans and trousers'),
('Jackets', 'Stylish jackets and coats'),
('Accessories', 'Fashion accessories and essentials')
ON CONFLICT DO NOTHING;

-- Insert sample products
INSERT INTO products (name, description, price, category_id, image, stock, featured) VALUES
('Classic White Shirt', 'Premium cotton white shirt perfect for formal occasions', 1299.00, 1, '/assets/images/white-shirt.jpg', 50, TRUE),
('Blue Denim Jeans', 'Comfortable blue denim jeans with modern fit', 2499.00, 2, '/assets/images/blue-jeans.jpg', 30, TRUE),
('Black Leather Jacket', 'Genuine leather jacket for stylish look', 5999.00, 3, '/assets/images/black-jacket.jpg', 15, TRUE),
('Striped Casual Shirt', 'Comfortable striped shirt for casual wear', 899.00, 1, '/assets/images/striped-shirt.jpg', 25, FALSE),
('Slim Fit Jeans', 'Modern slim fit denim jeans', 2199.00, 2, '/assets/images/slim-jeans.jpg', 20, FALSE),
('Denim Jacket', 'Classic denim jacket with modern styling', 3299.00, 3, '/assets/images/denim-jacket.jpg', 18, FALSE),
('Formal Black Shirt', 'Elegant black shirt for business meetings', 1499.00, 1, '/assets/images/black-shirt.jpg', 35, FALSE),
('Brown Leather Belt', 'Premium leather belt accessory', 499.00, 4, '/assets/images/brown-belt.jpg', 40, FALSE)
ON CONFLICT DO NOTHING;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);
CREATE INDEX IF NOT EXISTS idx_products_featured ON products(featured);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_cart_items_user ON cart_items(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);

-- Update function for updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON categories FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_cart_items_updated_at BEFORE UPDATE ON cart_items FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Create view for products with category names
CREATE OR REPLACE VIEW products_with_categories AS
SELECT 
    p.id,
    p.name,
    p.description,
    p.price,
    p.image,
    p.stock,
    p.status,
    p.featured,
    p.created_at,
    p.updated_at,
    c.name as category_name,
    c.description as category_description
FROM products p
LEFT JOIN categories c ON p.category_id = c.id;

-- Create view for order details
CREATE OR REPLACE VIEW order_details AS
SELECT 
    o.id,
    o.order_number,
    o.status,
    o.total_amount,
    o.shipping_address,
    o.payment_method,
    o.payment_status,
    o.created_at,
    u.name as customer_name,
    u.email as customer_email,
    COUNT(oi.id) as item_count
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id, u.name, u.email;
