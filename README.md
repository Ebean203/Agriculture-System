# 🌾 Lagonglong FARMS - Agriculture Management System

A comprehensive web-based system designed for the Municipal Agriculture Office (MAO) to manage farmer registrations, track agricultural inputs, monitor yields, and maintain comprehensive records for various agricultural programs in Lagonglong Municipality.

## 🎯 System Overview

**Lagonglong FARMS** is a full-featured agriculture management platform that streamlines the entire agricultural administration process - from farmer registration to yield monitoring, inventory management, and comprehensive reporting. The system serves as a centralized hub for MAO operations, supporting multiple government programs including RSBSA, NCFRS, and FishR.

## ✨ Core Features & Modules

### 🏠 **Dashboard & Analytics**
- **Real-time Statistics**: Live overview of farmers, commodities, inventory levels, and recent activities
- **Quick Action Cards**: Direct access to register farmers, record yields, manage inventory, and generate reports
- **Activity Feed**: Recent system activities and updates for transparency
- **Visual Analytics Dashboard**: Interactive charts and graphs for data insights
- **Performance Metrics**: KPIs for agricultural program monitoring

### 👨‍🌾 **Farmer Management System**
- **Comprehensive Registration**: Complete farmer profiles with personal, household, and agricultural information
- **Multi-Program Support**: 
  - **RSBSA** (Registry System for Basic Sectors in Agriculture)
  - **NCFRS** (National Convergence for Fisheries Registration System)
  - **FishR** (Fisherfolk Registration System)
- **Barangay Integration**: Location-based farmer organization and filtering
- **Archive System**: Maintain historical records without data loss
- **Photo Management**: Upload and manage farmer photos with geo-tagging capabilities
- **Search & Filter**: Advanced search functionality with real-time suggestions
- **Duplicate Prevention**: Automatic validation to prevent duplicate registrations

### 📊 **Yield Monitoring & Recording**
- **Real-time Yield Tracking**: Record crop yields with detailed farm visit information
- **Land Parcel Integration**: Link yields to specific land parcels and areas
- **Commodity Classification**: Categorized crop tracking (Rice, Corn, Vegetables, Fruits, etc.)
- **Quality Assessment**: Grade crops and track growth stages
- **Condition Monitoring**: Record field conditions, weather, and recommendations
- **Historical Data**: Maintain complete yield history for trend analysis
- **Visit Scheduling**: Track and schedule follow-up farm visits

### 🚢 **Maritime & Fisheries Management**
- **Boat Registration**: Comprehensive fishing vessel database
- **Vessel Specifications**: Track boat types, sizes, registration numbers, and ownership
- **NCFRS Integration**: Complete fisherfolk registration system
- **FishR Records**: Detailed fisherfolk profiles and activities
- **Maritime Equipment**: Track fishing gear and equipment distribution

### 📦 **Inventory & Distribution System**
- **Input Management**: Complete tracking of seeds, fertilizers, pesticides, and equipment
- **Real-time Stock Levels**: Live inventory monitoring with low-stock alerts
- **Distribution Logging**: Record and track input distribution to farmers
- **Visitation System**: Automated follow-up scheduling for distributed inputs
- **Category Management**: Organized input categories for efficient management
- **Supplier Tracking**: Monitor input sources and suppliers
- **Cost Tracking**: Financial monitoring of inventory investments

### 📈 **Reports & Analytics Engine**
- **Comprehensive Reports**: 
  - Farmer Registration Analytics
  - Commodity Production Reports
  - Yield Monitoring Summaries
  - Input Distribution Analysis
  - Inventory Status Reports
  - Barangay Analytics
- **Custom Date Ranges**: Flexible reporting periods
- **PDF Export**: Professional report generation with MAO branding
- **Automated Saving**: All reports automatically saved to database
- **Visual Charts**: Data visualization with Chart.js integration
- **Filtering Options**: Filter by barangay, commodity, date ranges, and more

### 🏛️ **Administrative Features**
- **Staff Management**: MAO personnel accounts with role-based access
- **Activity Logging**: Comprehensive audit trail of all system actions
- **Session Management**: Secure authentication and session handling
- **Settings Configuration**: System customization and maintenance options
- **Database Management**: Built-in tools for data maintenance
- **Backup Systems**: Data protection and recovery capabilities

### 🔧 **Advanced Technical Features**
- **Auto-Suggestion System**: Smart search with type-ahead functionality
- **Real-time Validation**: Instant form validation and error handling
- **AJAX Integration**: Smooth user experience without page reloads
- **Responsive Design**: Mobile-friendly interface for field use
- **File Upload Support**: Document attachments and proof handling
- **Transaction Safety**: Database transactions for data integrity
- **Error Handling**: Robust error management and user feedback

## 🛠️ Technical Specifications

### **Backend Technology**
- **PHP 7.4+**: Server-side scripting and business logic
- **MySQL/MariaDB**: Relational database management
- **Session Management**: Secure user authentication
- **File Handling**: Image and document upload capabilities

### **Frontend Technology**
- **Bootstrap 5**: Responsive CSS framework
- **JavaScript/jQuery**: Interactive user interface
- **Chart.js**: Data visualization and analytics
- **FontAwesome**: Icon library for enhanced UX
- **AJAX**: Asynchronous data processing

### **Database Schema**
The system uses a comprehensive database structure with interconnected tables:

#### **Core Tables**
- `farmers` - Main farmer registry with personal and agricultural data
- `land_parcels` - Land ownership and parcel information
- `yield_monitoring` - Crop yield tracking and monitoring data
- `household_info` - Household demographics and socioeconomic data

#### **Registration Systems**
- `rsbsa_registered_farmers` - RSBSA program participants
- `ncfrs_registered_farmers` - NCFRS maritime program participants
- `fisherfolk_registered_farmers` - FishR program participants
- `boats` - Fishing vessel registration and specifications

#### **Inventory & Distribution**
- `input_categories` - Types of agricultural inputs available
- `mao_inventory` - Current stock levels and inventory tracking
- `mao_distribution_log` - Input distribution history and records

#### **System Management**
- `mao_staff` - System users and staff accounts
- `activity_logs` - Comprehensive audit trail
- `barangays` - Geographic location references
- `commodities` - Agricultural product classifications
- `farmer_photos` - Photo storage with geo-tagging data

## 🚀 Installation & Setup

### **Prerequisites**
- XAMPP/WAMP/LAMP stack
- PHP 7.4 or higher
- MySQL 5.7 or MariaDB 10.3+
- Web browser (Chrome, Firefox, Safari, Edge)

### **Installation Steps**

1. **Clone/Download the System**
   ```bash
   git clone [repository-url]
   cd Agriculture-System-Clone
   ```

2. **Database Setup**
   - Import the SQL database file into MySQL
   - Update connection settings in `conn.php`:
   ```php
   $host = "localhost";
   $user = "root";
   $pass = "";
   $db = "agriculture-system";
   ```

3. **File Permissions**
   - Ensure write permissions for `/uploads` and `/reports` directories
   - Set proper permissions for photo storage

4. **Access the System**
   - Navigate to `http://localhost/Agriculture-System-Clone`
   - Login with administrator credentials

## 📱 System Navigation & Usage

### **Main Dashboard** (`index.php`)
Central hub showing real-time statistics, quick actions, and recent activities

### **Farmer Management** (`farmers.php`)
- Register new farmers with comprehensive forms
- View, edit, and manage existing farmer records
- Archive/restore farmer accounts
- Upload and manage farmer photos with geo-tagging

### **Yield Monitoring** (`yield_monitoring.php`)
- Record crop yields with detailed information
- Track multiple commodities and categories
- Monitor farming conditions and recommendations

### **Inventory Management** (`mao_inventory.php`)
- Monitor stock levels of agricultural inputs
- Record new inventory additions
- Track distribution to farmers

### **Reports & Analytics** (`reports.php`, `analytics_dashboard.php`)
- Generate comprehensive reports
- View visual analytics and charts
- Export reports as PDF documents

### **Maritime Records** (`boat_records.php`, `ncfrs_records.php`, `fishr_records.php`)
- Manage fishing vessel registrations
- Track fisherfolk and maritime activities
- Maintain NCFRS and FishR program records

## 🔐 Security Features

- **Session Management**: Secure login and session handling
- **SQL Injection Protection**: Prepared statements throughout
- **File Upload Validation**: Secure file handling and validation
- **Access Control**: Role-based permissions for different user types
- **Activity Logging**: Complete audit trail of all actions
- **Data Validation**: Client-side and server-side validation

## 📊 Key Performance Indicators

The system tracks and displays:
- Total registered farmers across all programs
- Active commodity types and production levels
- Inventory levels and distribution rates
- Yield trends and agricultural productivity
- Program participation rates (RSBSA, NCFRS, FishR)
- Barangay-wise agricultural statistics

## 🎯 Target Users

### **MAO Staff**
- Register and manage farmer information
- Record yield data during farm visits
- Distribute agricultural inputs
- Generate reports for program monitoring

### **MAO Administrators**
- Manage staff accounts and permissions
- Configure system settings
- Monitor overall system activities
- Generate comprehensive analytics

### **Field Officers**
- Mobile-friendly interface for field data collection
- Quick farmer lookup and registration
- Real-time yield recording capabilities

## 🔧 Maintenance & Support

### **Regular Maintenance**
- Database backup and optimization
- File cleanup and organization
- Performance monitoring
- Security updates

### **System Updates**
- Feature enhancements based on user feedback
- Bug fixes and security patches
- Performance improvements
- New report types and analytics

## 📈 Future Enhancements

- **Mobile Application**: Native mobile app for field operations
- **SMS Integration**: Automated notifications and alerts
- **Weather Integration**: Real-time weather data for farming decisions
- **Market Price Integration**: Current commodity price tracking
- **Advanced Analytics**: Machine learning for yield predictions
- **API Integration**: Connect with national agricultural databases

## 🤝 Contributing

This system is maintained by the Lagonglong Municipal Agriculture Office. For feature requests, bug reports, or improvements, please contact the system administrator.

## 📄 License

This system is developed for the exclusive use of Lagonglong Municipal Agriculture Office and is not licensed for distribution or modification without proper authorization.

## 📞 Support & Contact

**Municipal Agriculture Office - Lagonglong**
- **System Administrator**: [Contact Information]
- **Technical Support**: [Support Email/Phone]
- **Office Address**: [MAO Office Address]

---

## 🌟 System Statistics

**Database Management**: Comprehensive farmer registry with multi-program support
**Report Generation**: Automated PDF reports with professional formatting  
**Real-time Analytics**: Live dashboard with interactive charts and KPIs
**Mobile Responsive**: Optimized for field use on tablets and smartphones
**Security Compliant**: Follows government data protection standards
**Performance Optimized**: Fast loading times and efficient database queries

*Developed to support the agricultural development initiatives of Lagonglong Municipality and improve the efficiency of Municipal Agriculture Office operations.*