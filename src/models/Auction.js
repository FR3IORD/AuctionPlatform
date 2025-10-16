const mongoose = require('mongoose');

const auctionSchema = new mongoose.Schema({
  title: {
    type: String,
    required: [true, 'Title is required'],
    trim: true,
    maxlength: [100, 'Title cannot exceed 100 characters']
  },
  description: {
    type: String,
    required: [true, 'Description is required'],
    maxlength: [1000, 'Description cannot exceed 1000 characters']
  },
  startingPrice: {
    type: Number,
    required: [true, 'Starting price is required'],
    min: [0, 'Starting price must be positive']
  },
  currentPrice: {
    type: Number,
    required: true,
    min: 0
  },
  buyNowPrice: {
    type: Number,
    min: 0
  },
  seller: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  startTime: {
    type: Date,
    required: true,
    default: Date.now
  },
  endTime: {
    type: Date,
    required: [true, 'End time is required']
  },
  status: {
    type: String,
    enum: ['active', 'completed', 'cancelled'],
    default: 'active'
  },
  category: {
    type: String,
    required: [true, 'Category is required'],
    enum: ['electronics', 'art', 'collectibles', 'jewelry', 'vehicles', 'other']
  },
  images: [{
    type: String
  }],
  winner: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  },
  createdAt: {
    type: Date,
    default: Date.now
  }
});

// Validate that endTime is after startTime
auctionSchema.pre('save', function(next) {
  if (this.endTime <= this.startTime) {
    return next(new Error('End time must be after start time'));
  }
  if (!this.isModified('startingPrice') && !this.currentPrice) {
    this.currentPrice = this.startingPrice;
  }
  next();
});

module.exports = mongoose.model('Auction', auctionSchema);
