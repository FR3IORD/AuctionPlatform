# Changelog

All notable changes to AuctionPlatform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-10-16

### Added
- Complete platform redesign with modern architecture
- RESTful API with versioned endpoints (`/api/v2/`)
- User authentication and authorization system
  - JWT-based authentication
  - Role-based access control (user, admin)
  - Secure password hashing with bcrypt
- Auction management system
  - Create, read, update, delete operations
  - Multiple categories (electronics, art, collectibles, jewelry, vehicles, other)
  - Status tracking (active, completed, cancelled)
  - Automatic price updates
- Bidding system
  - Real-time bid placement
  - Bid validation (amount, balance, permissions)
  - Bid history tracking
  - Prevent seller self-bidding
- User profile management
  - Balance tracking
  - My auctions view
  - My bids view
- Advanced filtering and sorting
  - Filter by status, category
  - Sort by price, end time
- Comprehensive API documentation
- Test suite with Jest and Supertest
- Environment configuration with dotenv
- Error handling and validation
- CORS support for cross-origin requests

### Technical Details
- Node.js backend with Express.js
- MongoDB database with Mongoose ODM
- JWT authentication
- RESTful API design
- Comprehensive test coverage
- Docker-ready configuration

### Documentation
- Complete README with setup instructions
- API reference documentation
- Contributing guidelines
- Changelog

### Security
- Password hashing with bcrypt
- JWT token-based authentication
- Protected routes with middleware
- Input validation
- Role-based authorization

## [1.0.0] - Initial Release
- Initial repository setup
