import os
import json
import logging
from pymongo import MongoClient
from pymongo.errors import ConnectionFailure

logger = logging.getLogger(__name__)

class MongoDBService:
    """
    MongoDB Atlas integration via direct driver. 
    In a full MCP setup, this would be wrapped by the MCP server exposing tools.
    For this hackathon implementation, we simulate the interaction.
    """
    def __init__(self, uri: str = None, db_name: str = "moltdeals_ai"):
        self.uri = uri or os.getenv("MONGODB_URI")
        self.db_name = db_name
        self.client = None
        self.db = None
        
        if self.uri and "cluster0" not in self.uri:
            try:
                self.client = MongoClient(self.uri, serverSelectionTimeoutMS=5000)
                self.client.admin.command('ping')
                self.db = self.client[self.db_name]
                logger.info(f"Connected to MongoDB Atlas: {self.db_name}")
            except ConnectionFailure:
                logger.warning("Failed to connect to MongoDB Atlas. Using mock mode.")
                self.client = None
        else:
            logger.info("No valid MongoDB URI provided. Running in mock mode.")

    def save_deal(self, deal_data: dict) -> str:
        """Save an analyzed deal to MongoDB."""
        if self.db is not None:
            result = self.db.deals.insert_one(deal_data)
            return str(result.inserted_id)
        
        # Mock mode
        logger.info(f"[Mock MongoDB] Saved deal: {deal_data.get('title')}")
        return "mock_id_123"

    def vector_search_similar_deals(self, query_embedding: list, limit: int = 3) -> list:
        """
        Use Atlas Vector Search to find similar deals.
        Requires text-embedding-004 to be generated for the query.
        """
        if self.db is not None:
            # Placeholder for actual $vectorSearch pipeline
            pipeline = [
                {
                    "$vectorSearch": {
                        "index": "vector_index",
                        "path": "embedding",
                        "queryVector": query_embedding,
                        "numCandidates": limit * 10,
                        "limit": limit
                    }
                },
                {"$project": {"title": 1, "score": {"$meta": "vectorSearchScore"}}}
            ]
            try:
                return list(self.db.deals.aggregate(pipeline))
            except Exception as e:
                logger.error(f"Vector search failed: {e}")
                return []
        
        # Mock mode
        logger.info("[Mock MongoDB] Vector search executed.")
        return [{"title": "Mock Similar Deal", "score": 0.95}]
