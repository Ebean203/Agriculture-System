# 🌾 Agricultural Management System

A comprehensive web-based system designed for the Municipal Agriculture Office (MAO) to manage farmer registrations, track agricultural inputs, monitor yields, and maintain comprehensive records for various agricultural programs.

## ✨ Features

### 👨‍🌾 Farmer Management
- **Comprehensive Registration**: Complete farmer profiles with household information
- **Multi-Program Support**: RSBSA, NCFRS, and FishR registration tracking
- **Barangay Integration**: Location-based farmer organization
- **Archive System**: Maintain historical records of inactive farmers

### 📊 Yield Monitoring & Recording
- **Real-time Tracking**: Monitor crop yields with detailed visit records
- **Land Parcel Integration**: Link yields to specific land parcels
- **Quality Assessment**: Grade crops and track growth stages
- **Condition Monitoring**: Record field conditions and recommendations

### � Maritime & Fisheries
- **Boat Registration**: Track fishing vessels and boat ownership
- **NCFRS Integration**: National Convergence for Fisheries Registration System
- **FishR Records**: Comprehensive fisherfolk database
- **Vessel Management**: Boat types, registration numbers, and specifications

### 📦 Inventory & Distribution
- **Input Management**: Track seeds, fertilizers, pesticides, and equipment
- **Distribution Logging**: Record input distribution to farmers
- **Visitation Scheduling**: Automated follow-up for distributed inputs
- **Stock Monitoring**: Real-time inventory levels and updates

### � Analytics & Reporting
- **Dashboard Statistics**: Real-time overview of all system metrics
- **Activity Logging**: Comprehensive audit trail of all actions
- **Custom Reports**: Generate reports for various agricultural programs
- **Data Visualization**: Charts and graphs for better insights

### 🏛️ Administrative Features
- **Staff Management**: MAO personnel accounts and role management
- **Settings Configuration**: System customization and maintenance
- **Security**: Session management and access control
- **Backup & Recovery**: Data protection and system maintenance

## 🛠️ Tech Stack

- **Backend**: PHP 8.2+ with MySQLi
- **Database**: MySQL/MariaDB 10.4+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Tailwind CSS 3.x
- **Components**: Bootstrap 5.3
- **Icons**: Font Awesome 6.0
- **Server**: Apache (XAMPP/WAMP/LAMP)

## 🚀 Installation & Setup

### Prerequisites
- XAMPP/WAMP/LAMP stack
- PHP 8.2 or higher
- MySQL/MariaDB 10.4 or higher
- Modern web browser

### Installation Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/Ebean203/Agriculture-System.git
   cd Agriculture-System
   ```

2. **Setup XAMPP Environment**
   - Copy the project to your `C:\xampp\htdocs\` directory
   - Start Apache and MySQL services from XAMPP Control Panel

3. **Database Configuration**
   ```sql
   -- Create database
   CREATE DATABASE `agriculture-system`;
   
   -- Import the schema
   mysql -u root -p agriculture-system < agriculture-system.sql
   ```

4. **Configure Database Connection**
   ```php
   // Update conn.php with your settings
   $host = "localhost";
   $user = "root";
   $pass = "";
   $db = "agriculture-system";
   $port = 3307; // Adjust if using different port
   ```

5. **Access the System**
   - Open browser: `http://localhost/Agriculture-System/`
   - Default login: `username: admin`, `password: admin123`

## 📁 Project Structure

```
Agriculture-System/
├── 📄 index.php                    # Main dashboard & entry point
├── 🔐 login.php                    # Authentication system
├── 🔌 conn.php                     # Database connection
├── ✅ check_session.php            # Session validation
├── 👨‍🌾 farmers.php                 # Farmer management interface
├── 📊 yield_monitoring.php         # Yield tracking system
├── 📦 mao_inventory.php            # Inventory management
├── 🚢 rsbsa_records.php            # RSBSA registration records
├── 🎣 ncfrs_records.php            # NCFRS registration records
├── 🛥️ boat_records.php             # Boat registration records
├── 🐟 fishr_records.php            # FishR registration records
├── 👥 staff.php                    # MAO staff management
├── ⚙️ settings.php                 # System configuration
├── 📈 all_activities.php           # Activity logs & reports
├── 📋 farmer_regmodal.php          # Farmer registration modal
├── 📊 yield_record_modal.php       # Yield recording modal
├── 🔄 distribute_input.php         # Input distribution system
├── ✏️ farmer_editmodal.php         # Farmer profile editing
├── 📤 pdf_export.php               # Report generation
├── 🔧 update_inventory.php         # Inventory updates
├── 📊 record_yield.php             # Yield processing
├── 🚪 logout.php                   # Session termination
├── 📁 includes/                    # Helper functions & utilities
│   ├── 🏃 activity_logger.php      # Activity tracking
│   ├── 🎯 activity_icons.php       # UI icon mappings
│   ├── 📰 recent_activities.php    # Recent activity display
│   └── 🤝 visitation_helpers.php   # Input distribution helpers
└── 📖 README.md                    # Project documentation
```

## 🗃️ Database Schema

### Core Tables
- **farmers**: Main farmer registry with personal and agricultural data
- **land_parcels**: Land ownership and parcel information
- **yield_monitoring**: Crop yield tracking and monitoring data
- **household_info**: Household demographics and socioeconomic data

### Registration Systems
- **rsbsa_registered_farmers**: RSBSA program participants
- **ncfrs_registered_farmers**: NCFRS maritime program participants  
- **fisherfolk_registered_farmers**: FishR program participants
- **boats**: Fishing vessel registration and specifications

### Inventory & Distribution
- **input_categories**: Types of agricultural inputs available
- **mao_inventory**: Current stock levels and inventory tracking
- **mao_distribution_log**: Input distribution history and records

### System Management
- **mao_staff**: System users and staff accounts
- **activity_logs**: Comprehensive audit trail
- **barangays**: Geographic location references
- **commodities**: Agricultural product classifications

## 🎯 Key Features Explained

### Auto-Suggestion System
- **Farmer Search**: Type-ahead functionality for quick farmer selection
- **Real-time Filtering**: Instant search results as you type
- **Duplicate Prevention**: Automatic validation to prevent duplicate entries

### Responsive Dashboard
- **Statistics Cards**: Real-time metrics and KPIs
- **Quick Actions**: One-click access to common tasks
- **System Modules**: Organized navigation to all features
- **Activity Feed**: Recent system activities and updates

### Advanced Form Handling
- **Multi-step Registration**: Comprehensive farmer onboarding
- **Dynamic Validation**: Real-time form validation and error handling
- **File Upload Support**: Document attachments and proof handling
- **Transaction Safety**: Database transactions for data integrity

## 🔧 Configuration & Customization

### Database Configuration
```php
// conn.php - Adjust for your environment
$host = "localhost";     // Database host
$user = "root";          // Database username  
$pass = "";              // Database password
$db = "agriculture-system"; // Database name
$port = 3307;            // Database port (adjust if needed)
```

### System Settings
- **Barangay Management**: Add/edit local administrative areas
- **Commodity Categories**: Configure crop and product types
- **Input Categories**: Manage agricultural input types
- **Staff Roles**: Configure user permissions and access levels

## 🚀 Usage Guide

### For MAO Staff
1. **Login** with your credentials
2. **Register Farmers** using the comprehensive form
3. **Distribute Inputs** and track inventory
4. **Record Yields** during farm visits
5. **Generate Reports** for program monitoring

### For Administrators
1. **Manage Staff** accounts and permissions
2. **Configure System** settings and parameters
3. **Monitor Activities** through audit logs
4. **Backup Data** regularly for system safety

## 🧪 Testing & Development

### Local Development Setup
```bash
# Start XAMPP services
xampp-control.exe

# Navigate to project
cd C:\xampp\htdocs\Agriculture-System

# Check database connection
php -f conn.php

# Access development site
http://localhost/Agriculture-System/
```

### Common Issues & Solutions
- **MySQL Port Conflicts**: Update port in `conn.php` if MySQL runs on non-standard port
- **Permission Errors**: Ensure proper file permissions in htdocs directory
- **Session Issues**: Clear browser cookies and restart session

## 📱 Browser Compatibility

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (responsive design)

## 🔐 Security Features

- **Session Management**: Secure login/logout with session validation
- **SQL Injection Prevention**: Prepared statements and input sanitization
- **XSS Protection**: Output escaping and input validation
- **Access Control**: Role-based permissions and authentication
- **Activity Logging**: Comprehensive audit trail for all actions

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. **Fork the Repository**
   ```bash
   git fork https://github.com/Ebean203/Agriculture-System.git
   ```

2. **Create Feature Branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```

3. **Commit Changes**
   ```bash
   git commit -m "Add amazing feature"
   ```

4. **Push to Branch**
   ```bash
   git push origin feature/amazing-feature
   ```

5. **Open Pull Request**

### Contribution Guidelines
- Follow PHP PSR-12 coding standards
- Write clear commit messages
- Test your changes thoroughly
- Update documentation as needed
- Respect the existing code structure

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

## 🙋‍♂️ Support & Contact

- **Email**: [ebean203@gmail.com](mailto:ebean203@gmail.com)
- **GitHub Issues**: [Report a Bug](https://github.com/Ebean203/Agriculture-System/issues)
- **Documentation**: [Wiki](https://github.com/Ebean203/Agriculture-System/wiki)

## 🌟 Acknowledgments

- **Tailwind CSS** for the responsive UI framework
- **Bootstrap** for component styling
- **Font Awesome** for comprehensive iconography
- **PHP Community** for excellent documentation and support

---

**Made with ❤️ for Agricultural Development**

*Supporting farmers and agricultural development through technology*
