# AuctionPlatform V2.0

A modern, feature-rich auction platform built with Node.js, Express, and MongoDB. This platform enables users to create auctions, place bids, and manage their auction activities in real-time.

## Features

### Core Features
- **User Authentication & Authorization**: Secure JWT-based authentication system
- **Auction Management**: Create, read, update, and delete auctions
- **Real-time Bidding**: Place bids on active auctions with instant updates
- **User Profiles**: Manage user accounts with balance tracking
- **Role-based Access Control**: Support for user and admin roles
- **Advanced Filtering**: Filter auctions by status, category, and price

### V2.0 Enhancements
- RESTful API design with versioned endpoints (`/api/v2/`)
- Improved error handling and validation
- Comprehensive test coverage
- Enhanced security with bcrypt password hashing
- Auction categories (electronics, art, collectibles, jewelry, vehicles, other)
- Balance tracking for users
- Bid history tracking
- Prevent sellers from bidding on their own auctions
- Automatic auction status management

## Tech Stack

- **Backend**: Node.js, Express.js
- **Database**: MongoDB with Mongoose ODM
- **Authentication**: JWT (JSON Web Tokens)
- **Security**: bcryptjs for password hashing
- **Real-time**: Socket.io support (ready for implementation)
- **Testing**: Jest, Supertest

## Installation

1. Clone the repository:
```bash
git clone https://github.com/FR3IORD/AuctionPlatform.git
cd AuctionPlatform
```

2. Install dependencies:
```bash
npm install
```

3. Create a `.env` file in the root directory:
```env
PORT=3000
MONGODB_URI=mongodb://localhost:27017/auctionplatform
JWT_SECRET=your_jwt_secret_key_here
JWT_EXPIRE=7d
NODE_ENV=development
```

4. Start MongoDB (make sure MongoDB is running on your system)

5. Run the application:
```bash
# Development mode with auto-reload
npm run dev

# Production mode
npm start
```

## API Documentation

### Base URL
```
http://localhost:3000/api/v2
```

### Authentication Endpoints

#### Register User
```http
POST /api/v2/auth/register
Content-Type: application/json

{
  "username": "john_doe",
  "email": "john@example.com",
  "password": "password123"
}
```

#### Login
```http
POST /api/v2/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

#### Get Current User
```http
GET /api/v2/auth/me
Authorization: Bearer <token>
```

### Auction Endpoints

#### Create Auction
```http
POST /api/v2/auctions
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Vintage Camera",
  "description": "Rare vintage camera from the 1960s",
  "startingPrice": 100,
  "category": "electronics",
  "endTime": "2025-12-31T23:59:59Z"
}
```

#### Get All Auctions
```http
GET /api/v2/auctions?status=active&category=electronics&sort=price-asc
```

Query Parameters:
- `status`: active, completed, cancelled
- `category`: electronics, art, collectibles, jewelry, vehicles, other
- `sort`: price-asc, price-desc, ending-soon

#### Get Single Auction
```http
GET /api/v2/auctions/:id
```

#### Update Auction
```http
PUT /api/v2/auctions/:id
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Updated Title",
  "description": "Updated description"
}
```

#### Delete Auction
```http
DELETE /api/v2/auctions/:id
Authorization: Bearer <token>
```

#### Get My Auctions
```http
GET /api/v2/auctions/my-auctions
Authorization: Bearer <token>
```

### Bid Endpoints

#### Place Bid
```http
POST /api/v2/bids
Authorization: Bearer <token>
Content-Type: application/json

{
  "auctionId": "auction_id_here",
  "amount": 150
}
```

#### Get Auction Bids
```http
GET /api/v2/bids/auction/:auctionId
```

#### Get My Bids
```http
GET /api/v2/bids/my-bids
Authorization: Bearer <token>
```

### Health Check
```http
GET /api/v2/health
```

## Data Models

### User
- username (String, unique, required)
- email (String, unique, required)
- password (String, hashed, required)
- role (String: 'user' | 'admin')
- balance (Number, default: 0)
- createdAt (Date)

### Auction
- title (String, required)
- description (String, required)
- startingPrice (Number, required)
- currentPrice (Number)
- buyNowPrice (Number, optional)
- seller (Reference to User)
- startTime (Date)
- endTime (Date, required)
- status (String: 'active' | 'completed' | 'cancelled')
- category (String, required)
- images (Array of Strings)
- winner (Reference to User)
- createdAt (Date)

### Bid
- auction (Reference to Auction)
- bidder (Reference to User)
- amount (Number, required)
- createdAt (Date)

## Testing

Run the test suite:
```bash
# Run all tests
npm test

# Run tests in watch mode
npm run test:watch
```

## Project Structure

```
AuctionPlatform/
├── src/
│   ├── config/
│   │   └── database.js       # Database configuration
│   ├── controllers/
│   │   ├── authController.js # Authentication logic
│   │   ├── auctionController.js # Auction management
│   │   └── bidController.js  # Bidding logic
│   ├── middleware/
│   │   └── auth.js           # Authentication middleware
│   ├── models/
│   │   ├── User.js           # User schema
│   │   ├── Auction.js        # Auction schema
│   │   └── Bid.js            # Bid schema
│   ├── routes/
│   │   ├── auth.js           # Auth routes
│   │   ├── auctions.js       # Auction routes
│   │   └── bids.js           # Bid routes
│   ├── utils/
│   │   └── token.js          # JWT utilities
│   ├── app.js                # Express app setup
│   └── server.js             # Server entry point
├── tests/
│   └── api.test.js           # API tests
├── .env.example              # Environment variables template
├── .gitignore                # Git ignore rules
├── jest.config.js            # Jest configuration
├── package.json              # Project dependencies
└── README.md                 # This file
```

## Security Features

- JWT-based authentication
- Password hashing with bcrypt
- Protected routes with middleware
- Role-based access control
- Input validation
- Secure HTTP headers with CORS

## Business Rules

1. **Bidding Rules**:
   - Bid amount must be higher than current price
   - Sellers cannot bid on their own auctions
   - Users need sufficient balance to place bids
   - Bids can only be placed on active auctions

2. **Auction Rules**:
   - Auctions cannot be updated after receiving bids
   - End time must be after start time
   - Only sellers or admins can delete auctions

3. **User Rules**:
   - Unique username and email required
   - Passwords must be at least 6 characters
   - Usernames must be at least 3 characters

## Future Enhancements

- [ ] Real-time bid notifications with Socket.io
- [ ] Email notifications for auction events
- [ ] Image upload functionality
- [ ] Payment integration
- [ ] Advanced search and filters
- [ ] Watchlist functionality
- [ ] Auction analytics dashboard
- [ ] Mobile app support

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

ISC

## Author

FR3IORD

## Version

2.0.0
