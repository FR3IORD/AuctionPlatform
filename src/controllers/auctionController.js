const Auction = require('../models/Auction');
const Bid = require('../models/Bid');

// Create new auction
exports.createAuction = async (req, res) => {
  try {
    const auctionData = {
      ...req.body,
      seller: req.user._id,
      currentPrice: req.body.startingPrice
    };

    const auction = await Auction.create(auctionData);

    res.status(201).json({
      success: true,
      auction
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Get all auctions
exports.getAllAuctions = async (req, res) => {
  try {
    const { status, category, sort } = req.query;
    const query = {};

    if (status) {
      query.status = status;
    }

    if (category) {
      query.category = category;
    }

    let sortOption = { createdAt: -1 };
    if (sort === 'price-asc') {
      sortOption = { currentPrice: 1 };
    } else if (sort === 'price-desc') {
      sortOption = { currentPrice: -1 };
    } else if (sort === 'ending-soon') {
      sortOption = { endTime: 1 };
    }

    const auctions = await Auction.find(query)
      .populate('seller', 'username email')
      .populate('winner', 'username email')
      .sort(sortOption);

    res.status(200).json({
      success: true,
      count: auctions.length,
      auctions
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Get single auction
exports.getAuction = async (req, res) => {
  try {
    const auction = await Auction.findById(req.params.id)
      .populate('seller', 'username email')
      .populate('winner', 'username email');

    if (!auction) {
      return res.status(404).json({
        success: false,
        message: 'Auction not found'
      });
    }

    res.status(200).json({
      success: true,
      auction
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Update auction
exports.updateAuction = async (req, res) => {
  try {
    let auction = await Auction.findById(req.params.id);

    if (!auction) {
      return res.status(404).json({
        success: false,
        message: 'Auction not found'
      });
    }

    // Check if user is the seller
    if (auction.seller.toString() !== req.user._id.toString() && req.user.role !== 'admin') {
      return res.status(403).json({
        success: false,
        message: 'Not authorized to update this auction'
      });
    }

    // Don't allow updates if auction has bids
    const bidsCount = await Bid.countDocuments({ auction: auction._id });
    if (bidsCount > 0) {
      return res.status(400).json({
        success: false,
        message: 'Cannot update auction that has bids'
      });
    }

    auction = await Auction.findByIdAndUpdate(req.params.id, req.body, {
      new: true,
      runValidators: true
    });

    res.status(200).json({
      success: true,
      auction
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Delete auction
exports.deleteAuction = async (req, res) => {
  try {
    const auction = await Auction.findById(req.params.id);

    if (!auction) {
      return res.status(404).json({
        success: false,
        message: 'Auction not found'
      });
    }

    // Check if user is the seller or admin
    if (auction.seller.toString() !== req.user._id.toString() && req.user.role !== 'admin') {
      return res.status(403).json({
        success: false,
        message: 'Not authorized to delete this auction'
      });
    }

    await auction.deleteOne();

    res.status(200).json({
      success: true,
      message: 'Auction deleted successfully'
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};

// Get user's auctions
exports.getMyAuctions = async (req, res) => {
  try {
    const auctions = await Auction.find({ seller: req.user._id })
      .populate('winner', 'username email')
      .sort({ createdAt: -1 });

    res.status(200).json({
      success: true,
      count: auctions.length,
      auctions
    });
  } catch (error) {
    res.status(400).json({
      success: false,
      message: error.message
    });
  }
};
