import requests
import json
import sys

import os

# Base URL logic to be more portable
BASE_URL = os.environ.get("BASE_URL", "http://localhost/Tourist Guide System/api")
LOGIN_URL = f"{BASE_URL}/login.php"
CHAT_URL = f"{BASE_URL}/chat_ai.php"

USERNAME = "test_ai_user"
PASSWORD = "password123"

session = requests.Session()

def login():
    print(f"Logging in as {USERNAME}...")
    try:
        payload = {"username": USERNAME, "password": PASSWORD}
        response = session.post(LOGIN_URL, json=payload)
        response.raise_for_status()
        data = response.json()
        if data.get("success"):
            print("Login successful.")
            return True
        else:
            print(f"Login failed: {data.get('message')}")
            return False
    except Exception as e:
        print(f"Login error: {e}")
        return False

def ask_ai(message):
    try:
        payload = {"message": message}
        response = session.post(CHAT_URL, json=payload)
        response.raise_for_status()
        try:
            data = response.json()
            return data
        except json.JSONDecodeError:
            print(f"Failed to decode JSON: {response.text[:200]}")
            return None
    except Exception as e:
        print(f"Error asking AI: {e}")
        return None

def run_tests():
    if not login():
        return

    test_cases = [
        "Hello",
        "Who are you?",
        "What is this website?",
        "Top rated spots",
        "Where to eat?",
        "Tell me about Laguna",
        "How to get there?",
        "Events in Laguna",
        "Is there a festival?",
        "Where is Rizal Shrine?",
        "Rizal Shrine",
        "tell me about rizal shrine",
        "telll me abouut rizaaal shrin", # Typo test
        "swimming pools",
        "resorts",
        "contact admin",
        "logout",
        "login",
        "how to rate",
        "latest news about laguna", # Should trigger news check/web search logic
        "price of buko pie", # Should trigger web search check
        "meaning of life", # Random fallback
    ]

    results = []

    for msg in test_cases:
        print(f"\n--- Testing: '{msg}' ---")
        response = ask_ai(msg)
        if response:
            reply = response.get('reply', 'No reply field')
            debug = response.get('debug', [])
            confidence = response.get('confidence', 'N/A')
            print(f"Reply: {reply}")
            print(f"Confidence: {confidence}")
            # print(f"Debug: {debug}")
            
            results.append({
                "input": msg,
                "reply": reply,
                "confidence": confidence,
                "success": response.get('success', False)
            })
        else:
            print("No response or error.")
            results.append({
                "input": msg,
                "reply": "ERROR",
                "success": False
            })

    # Summary
    print("\n\n=== Test Summary ===")
    for r in results:
        status = "PASS" if r['success'] else "FAIL"
        print(f"[{status}] '{r['input']}' -> {r['reply'][:50]}...")

if __name__ == "__main__":
    run_tests()
