# AuctionBay - Premium Online Auction Platform

A modern, responsive auction platform built with PHP, MySQL, and Tailwind CSS.

## 🚀 Features

### User Features
- **User Registration & Authentication** - Secure signup/login with password hashing
- **Real-time Auction Participation** - Join live auctions and place strategic bids
- **Ticket-based Bidding System** - Purchase tickets that convert to bids (1 ticket = 5 bids)
- **Account Management** - View tickets, transactions, and auction history
- **Secure Payment Processing** - Add funds via Visa/Mastercard (demo mode)
- **Mobile-Responsive Design** - Perfect experience on all devices
- **Live Countdown Timers** - Real-time auction end time tracking

### Admin Features
- **Comprehensive Admin Panel** - Full platform management
- **Auction Management** - Create, edit, activate, and monitor auctions
- **User Management** - View and manage user accounts
- **Transaction Monitoring** - Track all financial transactions
- **Real-time Statistics** - Dashboard with key metrics
- **Status Controls** - Activate, complete, or cancel auctions

### Technical Features
- **Modern PHP Architecture** - Clean, object-oriented code structure
- **Secure Database Design** - Properly normalized with foreign keys
- **Responsive UI** - Tailwind CSS with custom components
- **Form Validation** - Client and server-side validation
- **Security Best Practices** - SQL injection prevention, XSS protection
- **Performance Optimized** - Efficient queries and caching headers

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## 🛠️ Installation

### 1. Database Setup
```sql
-- Import the database schema
mysql -u root -p < database_setup.sql
```

### 2. Configuration
```php
// Update config/database.php with your database credentials
private $host = 192.168.10.60
private $db_name = 'auction_platform';
private $username = 'root';
private $password = 'root';
```

### 3. Web Server Setup
- Place files in your web server document root
- Ensure mod_rewrite is enabled for pretty URLs
- Set appropriate file permissions (755 for directories, 644 for files)

### 4. Demo Accounts
```
Admin Account:
Email: admin@auctionbay.com
Password: admin123

User Account:
Email: user@auctionbay.com
Password: user123
```

## 🏗️ Project Structure

```
auction-platform/
├── config/                 # Configuration files
│   ├── config.php          # Main configuration
│   └── database.php        # Database connection
├── includes/               # Reusable components
│   ├── navbar.php          # Navigation component
│   ├── footer.php          # Footer component
│   └── auction-card.php    # Auction card component
├── auth/                   # Authentication system
│   ├── login.php           # User login
│   ├── register.php        # User registration
│   └── logout.php          # User logout
├── auctions/               # Auction system
│   ├── index.php           # Auction listing
│   └── view.php            # Auction details & bidding
├── account/                # User account management
│   ├── index.php           # Account dashboard
│   ├── tickets.php         # User tickets
│   ├── transactions.php    # Transaction history
│   └── add-funds.php       # Payment system
├── admin/                  # Admin panel
│   ├── index.php           # Admin dashboard
│   ├── auctions.php        # Manage auctions
│   ├── add-auction.php     # Create auctions
│   └── users.php           # Manage users
├── assets/                 # Static assets
│   ├── js/                 # JavaScript files
│   │   └── main.js         # Main JavaScript
│   └── css/                # CSS files
│       └── custom.css      # Custom styles
├── index.php               # Landing page
├── .htaccess              # Apache configuration
└── README.md              # Documentation
```

## 🎯 How Auctions Work

### For Users:
1. **Purchase Tickets** - Buy tickets for specific auctions (each ticket = 5 bids)
2. **Strategic Bidding** - Use bids wisely during live auctions
3. **Win Items** - Be the last bidder when the auction ends to win
4. **Collect Prizes** - Winners collect their items within one week

### For Admins:
1. **Create Auctions** - Set up new auctions with items, pricing, and timing
2. **Monitor Progress** - Track ticket sales and participant engagement
3. **Manage Status** - Activate, complete, or cancel auctions as needed
4. **View Analytics** - Access comprehensive statistics and reports

## 🔧 Key Technologies

- **Backend**: PHP 7.4+, MySQL
- **Frontend**: HTML5, Tailwind CSS, JavaScript
- **Security**: Password hashing, prepared statements, input validation
- **UI/UX**: Responsive design, smooth animations, intuitive navigation
- **Performance**: Optimized queries, caching, compressed assets

## 🚀 Deployment

### Production Checklist:
- [ ] Update database credentials
- [ ] Enable HTTPS redirects in .htaccess
- [ ] Configure real payment processing
- [ ] Set up proper error logging
- [ ] Enable production security headers
- [ ] Configure automated backups
- [ ] Set up monitoring and alerts

### Security Considerations:
- All user inputs are sanitized and validated
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars()
- CSRF protection on forms
- Secure session management
- Password hashing with PHP's password_hash()

## 📧 Support

For support, feature requests, or bug reports, please contact the development team.

## 📄 License

This project is proprietary software. All rights reserved.

---

Built with ❤️ for Luka Jibuti