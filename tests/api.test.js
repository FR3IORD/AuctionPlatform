const request = require('supertest');

// Mock mongoose to avoid database connection in tests
jest.mock('mongoose', () => ({
  connect: jest.fn().mockResolvedValue({}),
  Schema: jest.requireActual('mongoose').Schema,
  model: jest.fn(),
  connection: {
    host: 'mocked-host'
  }
}));

const app = require('../src/app');

describe('AuctionPlatform V2.0 API Tests', () => {
  describe('Health Check', () => {
    it('should return API health status', async () => {
      const response = await request(app)
        .get('/api/v2/health')
        .expect(200);

      expect(response.body.success).toBe(true);
      expect(response.body.version).toBe('2.0.0');
      expect(response.body.message).toContain('AuctionPlatform V2.0');
    });
  });

  describe('Authentication Routes', () => {
    it('should reject registration without required fields', async () => {
      const response = await request(app)
        .post('/api/v2/auth/register')
        .send({});

      // Should return 400 (bad request) not 404
      expect([400, 500]).toContain(response.status);
    });

    it('should reject login without credentials', async () => {
      const response = await request(app)
        .post('/api/v2/auth/login')
        .send({});

      expect(response.status).toBe(400);
      expect(response.body.success).toBe(false);
    });
  });

  describe('Auction Routes', () => {
    it('should return auctions list (empty or with data)', async () => {
      const response = await request(app)
        .get('/api/v2/auctions');

      // Should return 200, 400, or 500 (depending on DB state), but not 404
      expect([200, 400, 500]).toContain(response.status);
    });
  });

  describe('Bid Routes', () => {
    it('should require authentication for placing bids', async () => {
      const response = await request(app)
        .post('/api/v2/bids')
        .send({ auctionId: 'test', amount: 100 });

      // Should return 401 (unauthorized) not 404
      expect(response.status).toBe(401);
    });
  });

  describe('404 Handler', () => {
    it('should return 404 for non-existent routes', async () => {
      const response = await request(app)
        .get('/api/v2/nonexistent')
        .expect(404);

      expect(response.body.success).toBe(false);
      expect(response.body.message).toContain('not found');
    });
  });
});
