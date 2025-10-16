const express = require('express');
const router = express.Router();
const {
  placeBid,
  getAuctionBids,
  getMyBids
} = require('../controllers/bidController');
const { protect } = require('../middleware/auth');

router.post('/', protect, placeBid);
router.get('/auction/:auctionId', getAuctionBids);
router.get('/my-bids', protect, getMyBids);

module.exports = router;
