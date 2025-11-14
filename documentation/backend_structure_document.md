# Backend Structure Document

This document outlines the backend architecture, database setup, APIs, hosting, infrastructure, security, monitoring, and maintenance of the TokoKiara application. It is written in everyday language to ensure clarity for all readers.

## 1. Backend Architecture  

**Overview**  
TokoKiara’s backend is built as a single, cohesive application using the Laravel framework (PHP). It follows the Model-View-Controller (MVC) pattern, where:
- Models represent database tables and data logic.  
- Controllers handle business rules and connect models to views (or in our case, Inertia/React pages).  
- Views are delivered through Inertia.js, which bridges Laravel and React to create a smooth single‐page experience.

**Design Patterns and Frameworks**  
- MVC (Model-View-Controller) via Laravel  
- Eloquent ORM for easy, object-oriented database access  
- Inertia.js to serve React components without a separate API layer  
- Middleware layers for authentication, request logging, and input validation

**Scalability**  
- Horizontal scaling: multiple Laravel instances behind a load balancer  
- Stateless application servers: session data can live in a shared cache (e.g., Redis)  
- Database scaling: read replicas or sharding strategies for high‐volume setups

**Maintainability**  
- Clear folder structure (`app/Models`, `app/Http/Controllers`, `database/migrations`)  
- Database migrations and seeders keep schema and sample data in version control  
- Modular controllers and potential service classes keep code organized  
- Composer and NPM manage dependencies centrally

**Performance**  
- Query optimization via Eloquent eager loading and indexed columns  
- Caching of common queries or computed views (Redis)  
- Client-side optimizations (image compression, IndexedDB caching for offline use)

## 2. Database Management  

**Technology**  
- Type: SQL (Relational)  
- System: MySQL (can also run on MariaDB)

**Data Structure & Access**  
- Tables correspond to Eloquent models (Users, Products, Sales, Inventory, Contacts, Settings, Logs, etc.)  
- Migrations define table schemas and indexes in code  
- Seeders populate initial data for development or testing  
- Factories generate fake data for automated tests

**Best Practices**  
- Version-controlled migrations prevent schema drift  
- Foreign keys and indexes ensure data integrity and fast lookups  
- The `Userstamps` trait on models records who created or last updated each row  
- Regular backups using automated scripts or managed snapshots

## 3. Database Schema  

**Human-Readable Tables**  

1. Users  
   • id, name, email, password, role (admin/staff), created_at, updated_at, created_by, updated_by

2. Products  
   • id, sku, name, description, price, current_stock, reorder_threshold, barcode, image_path, created_by, updated_by

3. Sales  
   • id, product_id, quantity, sale_price, total_amount, sale_date, user_id, payment_method

4. Inventory Movements  
   • id, product_id, change_amount, movement_type (addition/removal), reference_id (e.g., sale or restock), date, user_id

5. Contacts  
   • id, type (customer/vendor), name, email, phone, address, outstanding_balance, created_by, updated_by

6. Charges & Refunds  
   • id, sale_id (nullable), amount, charge_type (refund/adjustment), date, user_id

7. Settings  
   • id, key (e.g., currency, tax_rate), value, updated_at

8. Logs  
   • id, level (info/warning/error), message, context (JSON), created_at

**SQL Definition (MySQL)**  
```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') DEFAULT 'staff',
  created_by INT,
  updated_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(50) UNIQUE,
  name VARCHAR(200) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  current_stock INT DEFAULT 0,
  reorder_threshold INT DEFAULT 0,
  barcode VARCHAR(100),
  image_path VARCHAR(255),
  created_by INT,
  updated_by INT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

-- Similar CREATE TABLE statements apply for sales, inventory_movements, contacts, charges, settings, and logs.
```  

## 4. API Design and Endpoints  

TokoKiara uses a mostly RESTful approach, with Laravel routes powering both Inertia page loads and JSON‐based API calls.

**Key Endpoints**  
- POST /login – authenticate a user  
- POST /register – new user signup (admin only)  
- GET /sales – list all sales records  
- POST /sales – create a new sale (POS)  
- GET /inventory – view current stock levels  
- PUT /inventory/{id} – adjust stock for a product  
- GET /products – list or search products  
- POST /products – add a new product  
- GET /contacts – list customers/vendors  
- POST /contacts – add a contact  
- GET /reports/sales?start=&end= – generate sales report  
- GET /settings – fetch global settings  
- PUT /settings – update system settings  
- GET /logs – view application logs  
- GET /barcodes/{product_id} – generate barcode image  
- POST /upload-image – handle image uploads

**How They Work**  
- Frontend sends HTTP requests (GET, POST, PUT) via Axios or Inertia.js  
- Laravel controllers validate input, run business logic, and return data or Inertia pages  
- JSON responses power dynamic UI components, while Inertia page loads render full views when necessary

## 5. Hosting Solutions  

**Cloud Provider**  
- Amazon Web Services (AWS) for core services  
- EC2 instances host the Laravel application  
- RDS (MySQL) provides managed relational database service  
- S3 for file and image storage  
- CloudFront as CDN for static assets (CSS, JS, images)

**Benefits**  
- Reliability: AWS SLAs guarantee high availability  
- Auto-scaling: automatically add or remove EC2 instances based on traffic  
- Cost-effectiveness: pay-as-you-go model and right-sizing of instances  
- Managed backups, security patches, and monitoring out of the box

## 6. Infrastructure Components  

- **Load Balancer** (AWS Application Load Balancer) distributes traffic across server instances  
- **Caching Layer** (Redis) for session storage, query caching, and queue backend  
- **Queue Service** (Laravel queues on Redis) processes background tasks like email sending or report generation  
- **Content Delivery Network** (CloudFront) caches and serves static files globally  
- **File Storage** (S3) holds user uploads and product images  
- **SSL Termination** via AWS Certificate Manager for secure HTTPS connections

These components collaborate to deliver fast, reliable responses. For example, the load balancer sends requests to healthy EC2 nodes, which may fetch data from Redis or RDS and return them via CloudFront-accelerated assets.

## 7. Security Measures  

- **Authentication & Authorization**  
  • Laravel’s built-in auth controllers protect routes  
  • Role-based access (admin vs. staff) restricts sensitive operations
- **Data Encryption**  
  • Passwords hashed with bcrypt  
  • HTTPS enforced for all traffic  
  • At-rest encryption for RDS and S3 buckets
- **Input Validation & CSRF Protection**  
  • Laravel request validation rules prevent malformed data  
  • CSRF tokens secure forms against cross-site attacks
- **Secure Headers & Rate Limiting**  
  • Middleware sets security headers (CSP, X-Frame, XSS protection)  
  • API rate limiting guards against brute-force and abuse
- **Audit Trails**  
  • `created_by`/`updated_by` fields record user actions  
  • Log entries capture errors and important events

## 8. Monitoring and Maintenance  

- **Logging**  
  • Monolog writes application logs to files and cloud log services  
  • Built-in log viewer lets admins search, filter, and paginate logs
- **Performance Monitoring**  
  • AWS CloudWatch tracks CPU, memory, disk, and network metrics  
  • Application performance tools (New Relic, DataDog) measure query times and page loads
- **Error Tracking**  
  • Sentry or Bugsnag captures unhandled exceptions in backend and frontend  

**Maintenance Practices**  
- Automated daily database backups with retention policies  
- Regular dependency updates via Composer and NPM  
- CI/CD pipelines run tests, lint code, and deploy only on passing builds  
- Scheduled migrations and seeders keep all environments in sync

## 9. Conclusion and Overall Backend Summary  

TokoKiara’s backend is a robust, scalable, and secure Laravel-based system tailored for retail and inventory management. By combining a clear MVC structure, reliable cloud services (AWS), and best practices in security and monitoring, it delivers strong performance and uptime. Its modular design and use of tools like Redis, Inertia.js, and Eloquent ORM ensure maintainability and rapid feature growth. Overall, this backend setup aligns closely with business goals of seamless sales processing, accurate inventory tracking, and insightful reporting—all while keeping data safe and the system easy to manage.