import os
import json
import logging
import requests

logger = logging.getLogger(__name__)

class MoltDealsAPI:
    """
    Client for interacting with the existing moltdeals.net PHP REST API.
    Enforces DRY_RUN to protect the live server.
    """
    def __init__(self, api_url: str = None, api_key: str = None, dry_run: bool = True):
        self.api_url = (api_url or os.getenv("MOLTDEALS_API_URL", "https://moltdeals.net/api")).rstrip('/')
        self.api_key = api_key or os.getenv("MOLTDEALS_API_KEY")
        # Ensure dry_run defaults to True to protect the live server
        self.dry_run = dry_run if dry_run is not None else os.getenv("DRY_RUN", "True").lower() in ('true', '1', 't')
        
        self.headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {self.api_key}" if self.api_key else ""
        }

    def post_deal(self, title: str, url: str, price: float, original_price: float, category: str, score: int) -> bool:
        payload = {
            "title": title,
            "url": url,
            "price": price,
            "original_price": original_price,
            "category": category,
            "ai_score": score
        }
        
        if self.dry_run:
            logger.info(f"[DRY RUN - PROTECTING SERVER] Would post deal to {self.api_url}/deals.php: {payload}")
            return True
            
        try:
            response = requests.post(f"{self.api_url}/deals.php", json=payload, headers=self.headers, timeout=10)
            response.raise_for_status()
            logger.info(f"Successfully posted deal: {title}")
            return True
        except requests.exceptions.RequestException as e:
            logger.error(f"Failed to post deal to MoltDeals API: {e}")
            return False

    def post_forum_comment(self, deal_id: int, comment: str) -> bool:
        payload = {
            "deal_id": deal_id,
            "content": comment
        }
        
        if self.dry_run:
            logger.info(f"[DRY RUN - PROTECTING SERVER] Would post comment to {self.api_url}/comments.php for deal {deal_id}: {comment}")
            return True
            
        try:
            response = requests.post(f"{self.api_url}/comments.php", json=payload, headers=self.headers, timeout=10)
            response.raise_for_status()
            logger.info(f"Successfully posted comment for deal {deal_id}")
            return True
        except requests.exceptions.RequestException as e:
            logger.error(f"Failed to post comment to MoltDeals API: {e}")
            return False
