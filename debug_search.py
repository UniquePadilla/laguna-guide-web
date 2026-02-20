import sys
import os
import json

# Add 'api' folder to path to import ai_helper
sys.path.append(os.path.join(os.path.dirname(__file__), 'api'))

try:
    from ai_helper import search_db, detect_intent, duckduckgo_search, get_db_connection, normalize_language, apply_synonyms, detect_topic_keywords, google_search, detect_intent_ai
    print("Successfully imported ai_helper logic.")
except ImportError as e:
    print(f"Error importing ai_helper: {e}")
    sys.exit(1)

# Configuration for testing (Optional: Fill these if you want to test Google Search)
TEST_GOOGLE_KEY = ""
TEST_GOOGLE_CX = ""
TEST_GEMINI_KEY = "YOUR_API_KEY" # Uses default from ai_helper if not set

def test_search(query):
    print(f"\n--- Testing Query: '{query}' ---")
    
    # 1. Clean
    clean_query = normalize_language(query)
    clean_query = apply_synonyms(clean_query)
    print(f"Cleaned: {clean_query}")
    
    # 2. Intent (Regex + AI)
    intents = detect_intent(clean_query)
    print(f"Intents (Regex): {intents}")
    
    # Optional: Test AI Intent
    # ai_intent = detect_intent_ai(query, TEST_GEMINI_KEY)
    # print(f"Intent (AI): {ai_intent}")
    
    # 3. Topic
    topic = detect_topic_keywords(query)
    print(f"Topic Keyword: {topic}")
    
    # 4. Search DB
    results = search_db(clean_query, intents)
    print(f"DB Results Found: {len(results)}")
    
    if results:
        for r in results:
            print(f" - {r['name']} ({r['category']}) [Featured: {r.get('featured', 0)}]")
    else:
        print("No DB results. Testing Web Search Fallback...")
        
        web_info = ""
        # 4a. Google Search
        if TEST_GOOGLE_KEY and TEST_GOOGLE_CX:
            print("Attempting Google Search...")
            web_info = google_search(query, TEST_GOOGLE_KEY, TEST_GOOGLE_CX)
            if web_info:
                print(f"[Google] Found: {web_info[:200]}...")
            else:
                print("[Google] No results.")
        
        # 4b. DuckDuckGo Search (Fallback)
        if not web_info:
            print("Attempting DuckDuckGo Search...")
            web_info = duckduckgo_search(query)
            if web_info:
                print(f"[DuckDuckGo] Found: {web_info[:200]}...")
            else:
                print("[DuckDuckGo] No results.")

if __name__ == "__main__":
    # Test cases
    queries = [
        "How to go to Pagsanjan Falls",
        "How to get to Laguna",
        "transport to Caliraya"
    ]
    
    for q in queries:
        test_search(q)
