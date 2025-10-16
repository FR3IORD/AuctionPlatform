const express = require('express');
const router = express.Router();
const {
  createAuction,
  getAllAuctions,
  getAuction,
  updateAuction,
  deleteAuction,
  getMyAuctions
} = require('../controllers/auctionController');
const { protect } = require('../middleware/auth');

router.route('/')
  .get(getAllAuctions)
  .post(protect, createAuction);

router.get('/my-auctions', protect, getMyAuctions);

router.route('/:id')
  .get(getAuction)
  .put(protect, updateAuction)
  .delete(protect, deleteAuction);

module.exports = router;
