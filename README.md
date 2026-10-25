# Clothes Shop Retail Management ERP & POS System

A high-performance, full-featured retail management ERP and Point of Sale (POS) application built for clothing stores, boutiques, readymade garment shops, and textile retailers.

Developed using **Core PHP, MySQL (PDO), HTML5, CSS3, JavaScript, jQuery, locally hosted Bootstrap 5, Chart.js, and JsBarcode** without external framework overhead.

---

## 🚀 Key Modules & Capabilities

### 1. High-Speed Point of Sale (POS) & Billing
- **Instant Barcode Scanner Support**: Hardware laser scanner compatible (instant scan & add).
- **Interactive Size & Color Picker**: Visual product cards open a quick modal showing in-stock sizes and colors.
- **Cart Engine**: Dynamic quantity controls, price override with permissions, item-level & bill-level discounts.
- **Split & Digital Payments**: Cash, JazzCash, Easypaisa, Bank Transfer, Card, or Split Payment across multiple methods.
- **Customer Khata (Credit) Integration**: Balance due automatically posts to customer credit ledger.
- **Hold / Resume Sales**: Park active carts (`F4`) and recall them seamlessly.
- **Dual Invoice Formats**:
  - **80mm / 58mm Thermal Receipts** (`print_receipt.php`) with shop header, size/color variant lines, policy notices, and Code-128 barcodes.
  - **A4 Corporate Sales Tax Invoices** (`print_invoice.php`).
- **Keyboard Shortcuts**: `F2` (Barcode search), `F4` (Hold sale), `F8` (Pay & Bill), `Enter` (Scan).

### 2. Clothing Variant & Stock Matrix (The Differentiating Feature)
- **Size x Color 2D Stock Grid**: View and manage all garment sizes (S, M, L, XL, 30, 32, etc.) and colors (Black, Navy, White, etc.) in a matrix table.
- **Row & Column Dynamic Totals**: Real-time stock counts by size and by color.
- **Batch Pricing**: One-click fill for purchase cost, retail price, and wholesale price across matrix variants.
- **Bulk Matrix Save**: Atomic updates and stock audit tracking.

### 3. Inventory, Stock Movements & Damage Tracking
- **Real-Time Stock Tracking**: Accurate stock per size-color variant.
- **Stock Audit Ledger**: Complete chronological ledger of every stock increase and decrease (purchases, sales, returns, damages, physical count differences).
- **Physical Adjustments**: Track damaged stock, tears, fabric defects, and physical audit variations with reasons.
- **Stock Valuation**: Real-time valuation at purchase cost, retail value, and potential gross profit margin.

### 4. Supplier Purchases & Khata Ledger
- **Stock Receiving**: Receive bulk garments by variant (size and color).
- **Costing & Freight**: Track supplier discounts, freight, and net purchase cost.
- **Supplier Ledger (Khata)**: Tracks bills, payments, and running balance payable.
- **Payment Vouchers**: Record cash or bank payouts with voucher receipts.

### 5. Customer Khata (Credit Ledger)
- **Customer Directory**: Walk-in counter customers and registered credit accounts.
- **Credit Limits & Dues**: Real-time overdue balance tracking and warnings.
- **Payment Collections**: Receive cash, JazzCash, or bank payments against Khata with automated receipt generation.
- **Loyalty Points**: Automatically rewards points on purchases.

### 6. Sales Returns & Size/Color Exchanges
- **Invoice Search**: Scan or enter invoice number to retrieve purchased items and remaining eligible return quantity.
- **Regular Returns**: Restock sellable inventory or mark defective; refund in cash or credit to customer Khata.
- **Product Exchanges**: Swap one size/color for another (e.g., Size M swapped for Size L). Calculates the exact price difference and settles the balance.

### 7. Operating Expenses & Cash Drawer (Shift Management)
- **Expense Vouchers**: Track rent, staff salaries, electricity, packaging bags, and refreshments.
- **Cash Register Shifts**:
  - Open shift with opening cash float.
  - Reconcile shift: Opening + Sales Cash + Khata collections - Expenses - Supplier payouts - Refunds.
  - Close shift with actual cash count and difference calculation (Over/Short).

### 8. Financial & Accounting Reports
- **Profit & Loss Statement (Income Statement)**:
  - Gross Sales - Discounts - Returns = **Net Revenue**
  - Net Revenue - COGS = **Gross Operating Profit**
  - Gross Profit - Operating Expenses = **Net Operating Profit (EBITDA)**
- **Balance Sheet**: Assets (Cash, Receivables, Stock at Cost) vs Liabilities (Supplier Payables) and Owner's Equity.
- **Cash Flow Statement**: Cash Inflows vs Cash Outflows and Net Cash Position.
- **Clothing Category Profitability**: Sales, COGS, Gross Profit, and Margin % per clothing department.

### 9. Charts & Visual Business Analytics
- Interactive Chart.js graphs on Dashboard & Reports:
  - 7-Day Revenue & Gross Profit Trend (Smooth line & area chart).
  - Category Share (Donut chart).
  - Best-Selling Sizes (Bar chart).
  - Payment Methods Mix (Pie chart).

### 10. Barcode & Price Tag Sticker Generator
- Custom barcode label generator supporting thermal label rolls (50x30mm) and A4 sticker sheets.
- Includes Store Name, Garment Name, Size, Color, Price, and Code-128 SVG barcode.

---

## 🔑 Default User Accounts

| Role | Username | Password | Access Level |
|---|---|---|---|
| **Administrator / Owner** | `admin` | `admin123` | Full ERP, Settings, Reports, Matrix, POS |
| **Cashier** | `cashier` | `cashier123` | POS Billing, Register Shifts, Receipts |
| **Manager** | `manager` | `admin123` | Sales, Purchases, Matrix, Returns, Inventory |
| **Accountant** | `accountant` | `admin123` | Ledgers, Expenses, Financial Reports |

---

## 🗄️ Database Setup & Installation

- **Database Name**: `clothes_retail_db`
- **Port**: `3306`
- **Default Database User**: `root` (no password)
- Schema and rich seed data are stored in `database.sql` and already executed.
- Access URL: `http://localhost/ClothesRetail/`
