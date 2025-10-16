const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');

// Load env vars
dotenv.config();

// Route files
const authRoutes = require('./routes/auth');
const auctionRoutes = require('./routes/auctions');
const bidRoutes = require('./routes/bids');

const app = express();

// Body parser
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Enable CORS
app.use(cors());

// Mount routers
app.use('/api/v2/auth', authRoutes);
app.use('/api/v2/auctions', auctionRoutes);
app.use('/api/v2/bids', bidRoutes);

// Health check route
app.get('/api/v2/health', (req, res) => {
  res.status(200).json({
    success: true,
    message: 'AuctionPlatform V2.0 API is running',
    version: '2.0.0'
  });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: 'Route not found'
  });
});

// Error handler
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(err.status || 500).json({
    success: false,
    message: err.message || 'Server Error'
  });
});

module.exports = app;
