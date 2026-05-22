import os
import sys
import logging
import json
from dotenv import load_dotenv

# Ensure we can import from the root directory
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

from agents.orchestrator import OrchestratorAgent

def main():
    print("\n" + "="*60)
    print("MoltDeals Multi-Agent AI Pipeline - Hackathon Demo")
    print("Partner Integrations: MongoDB Atlas Vector Search, Dynatrace")
    print("DRY_RUN is enabled. No data will be written to the live server.")
    print("="*60 + "\n")
    
    orchestrator = OrchestratorAgent()
    results = orchestrator.run_pipeline()
    
    # Save output for inspection
    output_file = os.path.join(os.path.dirname(__file__), 'sample_output.json')
    with open(output_file, 'w') as f:
        json.dump(results, f, indent=4)
        
    print(f"\n[SUCCESS] Demo complete. Output saved to {output_file}")

if __name__ == "__main__":
    main()
