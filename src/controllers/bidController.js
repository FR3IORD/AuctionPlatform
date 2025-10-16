const Bid = require('../models/Bid');
const Auction = require('../models/Auction');

// Place a bid
exports.placeBid = async (req, res) => {
  try {
    const { auctionId, amount } = req.body;

    // Get auction
    const auction = await Auction.findById(auctionId);
    if (!auction) {
      return res.status(404).json({
        success: false,
        message: 'Auction not found'
      });
    }

    // Check if auction is active
    if (auction.status !== 'active') {
      return res.status(400).json({
        success: false,
        message: 'Auction is not active'
      });
    }

    // Check if auction has ended
    if (new Date() > auction.endTime) {
      return res.status(400).json({
        success: false,
        message: 'Auction has ended'
      });
    }

    // Check if user is the seller
    if (auction.seller.toString() === req.user._id.toString()) {
      return res.status(400).json({
        success: false,
        message: 'Seller cannot bid on their own auction'
      });
    }

    // Validate bid amount
    if (amount <= auction.currentPrice) {
      return res.status(400).json({
        success: false,
        message: `Bid amount must be higher than current price of $${auction.currentPrice}`
      });
    }

    // Check if user has sufficient balance
    if (req.user.balance < amount) {
      return res.status(400).json({
        success: false,
        message: 'Insufficient balance to place this bid'
      });
    }

    // Create bid
    const bid = await Bid.create({
      auction: auctionId,
      bidder: req.user._id,
      amount
    });

    // Update auction current price
    auction.currentPrice = amount;
    await auction.save();

    // Populate bid data
    await bid.populate('bidder', 'username email');

    res.status(201).json({
      success: true,
      bid,
      auction: {
        id: auction._id,
        currentPrice: auction.currentPrice
      }
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Get bids for an auction
exports.getAuctionBids = async (req, res) => {
  try {
    const bids = await Bid.find({ auction: req.params.auctionId })
      .populate('bidder', 'username email')
      .sort({ createdAt: -1 });

    res.status(200).json({
      success: true,
      count: bids.length,
      bids
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Get user's bids
exports.getMyBids = async (req, res) => {
  try {
    const bids = await Bid.find({ bidder: req.user._id })
      .populate('auction', 'title currentPrice endTime status')
      .sort({ createdAt: -1 });

    res.status(200).json({
      success: true,
      count: bids.length,
      bids
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};
