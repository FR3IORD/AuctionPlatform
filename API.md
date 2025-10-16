# AuctionPlatform V2.0 API Reference

## Overview
This document provides detailed information about the AuctionPlatform V2.0 RESTful API endpoints.

## Base URL
```
http://localhost:3000/api/v2
```

## Authentication
Most endpoints require authentication using JWT tokens. Include the token in the Authorization header:
```
Authorization: Bearer <your_jwt_token>
```

## Response Format
All responses follow this structure:
```json
{
  "success": true|false,
  "message": "Response message",
  "data": {}
}
```

## Error Codes
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `500`: Server Error

---

## Authentication Endpoints

### Register User
Creates a new user account.

**Endpoint:** `POST /auth/register`

**Body:**
```json
{
  "username": "string (3+ chars, unique)",
  "email": "string (valid email, unique)",
  "password": "string (6+ chars)"
}
```

**Response (201):**
```json
{
  "success": true,
  "token": "jwt_token_here",
  "user": {
    "id": "user_id",
    "username": "john_doe",
    "email": "john@example.com",
    "role": "user",
    "balance": 0
  }
}
```

### Login
Authenticate an existing user.

**Endpoint:** `POST /auth/login`

**Body:**
```json
{
  "email": "string",
  "password": "string"
}
```

**Response (200):**
```json
{
  "success": true,
  "token": "jwt_token_here",
  "user": {
    "id": "user_id",
    "username": "john_doe",
    "email": "john@example.com",
    "role": "user",
    "balance": 0
  }
}
```

### Get Current User
Get the authenticated user's profile.

**Endpoint:** `GET /auth/me`

**Headers:** `Authorization: Bearer <token>`

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "user_id",
    "username": "john_doe",
    "email": "john@example.com",
    "role": "user",
    "balance": 0
  }
}
```

---

## Auction Endpoints

### Create Auction
Create a new auction (requires authentication).

**Endpoint:** `POST /auctions`

**Headers:** `Authorization: Bearer <token>`

**Body:**
```json
{
  "title": "string (max 100 chars)",
  "description": "string (max 1000 chars)",
  "startingPrice": "number (positive)",
  "buyNowPrice": "number (optional, positive)",
  "category": "electronics|art|collectibles|jewelry|vehicles|other",
  "endTime": "ISO 8601 date string",
  "images": ["url1", "url2"] // optional
}
```

**Response (201):**
```json
{
  "success": true,
  "auction": {
    "id": "auction_id",
    "title": "Vintage Camera",
    "description": "Rare vintage camera from the 1960s",
    "startingPrice": 100,
    "currentPrice": 100,
    "seller": "user_id",
    "startTime": "2025-10-16T10:40:00Z",
    "endTime": "2025-12-31T23:59:59Z",
    "status": "active",
    "category": "electronics",
    "createdAt": "2025-10-16T10:40:00Z"
  }
}
```

### Get All Auctions
Retrieve all auctions with optional filtering.

**Endpoint:** `GET /auctions`

**Query Parameters:**
- `status` (optional): `active`, `completed`, `cancelled`
- `category` (optional): `electronics`, `art`, `collectibles`, `jewelry`, `vehicles`, `other`
- `sort` (optional): `price-asc`, `price-desc`, `ending-soon`, or default (newest first)

**Response (200):**
```json
{
  "success": true,
  "count": 10,
  "auctions": [
    {
      "id": "auction_id",
      "title": "Vintage Camera",
      "seller": {
        "id": "user_id",
        "username": "john_doe",
        "email": "john@example.com"
      },
      "currentPrice": 150,
      "status": "active",
      "endTime": "2025-12-31T23:59:59Z"
    }
  ]
}
```

### Get Single Auction
Retrieve detailed information about a specific auction.

**Endpoint:** `GET /auctions/:id`

**Response (200):**
```json
{
  "success": true,
  "auction": {
    "id": "auction_id",
    "title": "Vintage Camera",
    "description": "Rare vintage camera from the 1960s",
    "startingPrice": 100,
    "currentPrice": 150,
    "seller": {
      "id": "user_id",
      "username": "john_doe",
      "email": "john@example.com"
    },
    "winner": null,
    "status": "active",
    "category": "electronics",
    "startTime": "2025-10-16T10:40:00Z",
    "endTime": "2025-12-31T23:59:59Z",
    "createdAt": "2025-10-16T10:40:00Z"
  }
}
```

### Update Auction
Update an auction (only if no bids have been placed).

**Endpoint:** `PUT /auctions/:id`

**Headers:** `Authorization: Bearer <token>`

**Body:** (any fields to update)
```json
{
  "title": "Updated Title",
  "description": "Updated description"
}
```

**Response (200):**
```json
{
  "success": true,
  "auction": { /* updated auction object */ }
}
```

**Errors:**
- `403`: Not authorized (not the seller or admin)
- `400`: Cannot update auction that has bids

### Delete Auction
Delete an auction (seller or admin only).

**Endpoint:** `DELETE /auctions/:id`

**Headers:** `Authorization: Bearer <token>`

**Response (200):**
```json
{
  "success": true,
  "message": "Auction deleted successfully"
}
```

### Get My Auctions
Get all auctions created by the authenticated user.

**Endpoint:** `GET /auctions/my-auctions`

**Headers:** `Authorization: Bearer <token>`

**Response (200):**
```json
{
  "success": true,
  "count": 5,
  "auctions": [ /* array of auction objects */ ]
}
```

---

## Bid Endpoints

### Place Bid
Place a bid on an auction.

**Endpoint:** `POST /bids`

**Headers:** `Authorization: Bearer <token>`

**Body:**
```json
{
  "auctionId": "string",
  "amount": "number (must be > current price)"
}
```

**Response (201):**
```json
{
  "success": true,
  "bid": {
    "id": "bid_id",
    "auction": "auction_id",
    "bidder": {
      "id": "user_id",
      "username": "jane_doe",
      "email": "jane@example.com"
    },
    "amount": 150,
    "createdAt": "2025-10-16T10:40:00Z"
  },
  "auction": {
    "id": "auction_id",
    "currentPrice": 150
  }
}
```

**Errors:**
- `400`: Bid amount too low
- `400`: Insufficient balance
- `400`: Auction not active or has ended
- `400`: Seller cannot bid on own auction

### Get Auction Bids
Get all bids for a specific auction.

**Endpoint:** `GET /bids/auction/:auctionId`

**Response (200):**
```json
{
  "success": true,
  "count": 3,
  "bids": [
    {
      "id": "bid_id",
      "bidder": {
        "id": "user_id",
        "username": "jane_doe",
        "email": "jane@example.com"
      },
      "amount": 150,
      "createdAt": "2025-10-16T10:40:00Z"
    }
  ]
}
```

### Get My Bids
Get all bids placed by the authenticated user.

**Endpoint:** `GET /bids/my-bids`

**Headers:** `Authorization: Bearer <token>`

**Response (200):**
```json
{
  "success": true,
  "count": 7,
  "bids": [
    {
      "id": "bid_id",
      "auction": {
        "id": "auction_id",
        "title": "Vintage Camera",
        "currentPrice": 150,
        "endTime": "2025-12-31T23:59:59Z",
        "status": "active"
      },
      "amount": 150,
      "createdAt": "2025-10-16T10:40:00Z"
    }
  ]
}
```

---

## Health Check

### API Health
Check if the API is running.

**Endpoint:** `GET /health`

**Response (200):**
```json
{
  "success": true,
  "message": "AuctionPlatform V2.0 API is running",
  "version": "2.0.0"
}
```

---

## Rate Limiting
Currently not implemented, but recommended for production:
- 100 requests per 15 minutes per IP for general endpoints
- 10 requests per minute for authentication endpoints

## Pagination
Not currently implemented, but planned for future versions. Will use:
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 10, max: 100)

## WebSocket Events (Future)
Real-time updates will be available via Socket.io:
- `bid:placed` - When a new bid is placed
- `auction:started` - When an auction starts
- `auction:ending` - 5 minutes before auction ends
- `auction:ended` - When an auction ends

## Changelog

### Version 2.0.0
- Complete platform redesign
- RESTful API with versioned endpoints
- Enhanced authentication and security
- Improved auction and bid management
- Comprehensive API documentation
- Test coverage
