import sys
import json
import os

# Add current directory to sys.path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from ai_helper import detect_topic_ai, handle_distance_query, detect_intent, handle_unknown_place, should_use_unknown_place_handler

def test_distance_query():
    print("\n--- Testing Distance Query ---")
    query = "how long km calamba to uplb"
    intents = detect_intent(query)
    print(f"Query: {query}")
    print(f"Intents: {intents}")
    
    if "distance" in intents:
        response = handle_distance_query(query, intents)
        print("Response found.")
        if "Estimated Distance" in response and "Travel Time" in response:
            print("✅ PASS: Distance info found")
        else:
            print("❌ FAIL: Distance info missing")
    else:
        print("❌ FAIL: Distance intent not detected")

def test_unknown_place_query():
    print("\n--- Testing Unknown Place Query ---")
    query = "tell me about famy"
    db_results = [] # Simulate empty DB
    use_fallback, place_name = should_use_unknown_place_handler(query, db_results)
    print(f"Query: {query}")
    print(f"Use fallback: {use_fallback}, Place: {place_name}")
    
    if use_fallback and place_name == "famy":
        response = handle_unknown_place(place_name, query)
        if "rice terraces" in response or "Rice terraces" in response:
             print("✅ PASS: Famy info found (rice terraces)")
        else:
             print("❌ FAIL: Famy info missing")
    else:
        print("❌ FAIL: Fallback not triggered")

if __name__ == "__main__":
    test_distance_query()
    test_unknown_place_query()
