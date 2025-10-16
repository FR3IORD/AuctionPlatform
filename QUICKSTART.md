# AuctionPlatform V2.0 - Quick Start Guide

Get started with AuctionPlatform V2.0 in minutes!

## Prerequisites

- Node.js 18+ installed
- MongoDB running locally or accessible remotely
- npm or yarn package manager

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/FR3IORD/AuctionPlatform.git
cd AuctionPlatform
```

### 2. Install Dependencies
```bash
npm install
```

### 3. Configure Environment
Create a `.env` file in the root directory:
```bash
cp .env.example .env
```

Edit `.env` with your configuration:
```env
PORT=3000
MONGODB_URI=mongodb://localhost:27017/auctionplatform
JWT_SECRET=your_secure_secret_key_here
JWT_EXPIRE=7d
NODE_ENV=development
```

### 4. Start MongoDB
Make sure MongoDB is running:
```bash
# If using MongoDB service
sudo systemctl start mongodb

# Or if using Docker
docker run -d -p 27017:27017 --name mongodb mongo:7
```

### 5. Start the Application
```bash
# Development mode (with auto-reload)
npm run dev

# Production mode
npm start
```

The API will be available at `http://localhost:3000`

## Quick Test

### 1. Check API Health
```bash
curl http://localhost:3000/api/v2/health
```

Expected response:
```json
{
  "success": true,
  "message": "AuctionPlatform V2.0 API is running",
  "version": "2.0.0"
}
```

### 2. Register a User
```bash
curl -X POST http://localhost:3000/api/v2/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "password": "password123"
  }'
```

Save the returned JWT token for authenticated requests.

### 3. Create an Auction
```bash
curl -X POST http://localhost:3000/api/v2/auctions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "title": "Vintage Camera",
    "description": "A beautiful vintage camera from the 1960s",
    "startingPrice": 100,
    "category": "electronics",
    "endTime": "2025-12-31T23:59:59Z"
  }'
```

### 4. View Auctions
```bash
curl http://localhost:3000/api/v2/auctions
```

## Docker Quick Start

If you prefer using Docker:

### 1. Using Docker Compose (Recommended)
```bash
# Build and start all services (MongoDB + API)
docker-compose up -d

# Check logs
docker-compose logs -f

# Stop services
docker-compose down
```

### 2. Using Docker Only
```bash
# Start MongoDB
docker run -d -p 27017:27017 --name mongodb mongo:7

# Build the application
docker build -t auction-platform .

# Run the application
docker run -d -p 3000:3000 \
  -e MONGODB_URI=mongodb://host.docker.internal:27017/auctionplatform \
  -e JWT_SECRET=your_secret_key \
  --name auction-app \
  auction-platform
```

## Testing

Run the test suite:
```bash
npm test
```

## API Documentation

For complete API documentation, see [API.md](./API.md)

Key endpoints:
- `POST /api/v2/auth/register` - Register user
- `POST /api/v2/auth/login` - Login user
- `GET /api/v2/auctions` - Get all auctions
- `POST /api/v2/auctions` - Create auction (auth required)
- `POST /api/v2/bids` - Place bid (auth required)

## Common Issues

### MongoDB Connection Error
**Problem:** Cannot connect to MongoDB  
**Solution:** Ensure MongoDB is running and the URI in `.env` is correct

### Port Already in Use
**Problem:** Port 3000 is already in use  
**Solution:** Change the PORT in `.env` or stop the process using port 3000

### JWT Token Invalid
**Problem:** "Not authorized" error  
**Solution:** Ensure the token is included in the Authorization header as `Bearer <token>`

## Next Steps

1. Read the full [README.md](./README.md) for detailed information
2. Explore [API.md](./API.md) for complete API reference
3. Check [CONTRIBUTING.md](./CONTRIBUTING.md) to contribute
4. Review [CHANGELOG.md](./CHANGELOG.md) for version history

## Support

For issues, questions, or contributions, please:
1. Check the documentation
2. Search existing issues on GitHub
3. Create a new issue if needed

## License

ISC - See LICENSE file for details
