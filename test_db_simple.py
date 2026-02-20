import sys
import os
sys.path.append(os.path.join(os.path.dirname(__file__), 'api'))
from ai_helper import search_db

def run_test(name, query, intents):
    print(f"\n--- Test: {name} ---")
    try:
        results = search_db(query, intents)
        print(f"Success! Found {len(results)} results.")
    except Exception as e:
        print(f"FAILED: {e}")

if __name__ == "__main__":
    # Test 6: Keywords + Tourist Spot Intent
    run_test("Tourist Spot Intent", "pagsanjan", ["tourist_spot"])
