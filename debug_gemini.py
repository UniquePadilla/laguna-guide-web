
import requests
import json
import sys

API_KEY = "AIzaSyAJqootuQyeuuSLCCVoTcfjDDVLUWkEO3M"

MODELS_TO_TEST = [
    "gemini-1.5-flash",
    "gemini-1.5-flash-latest",
    "gemini-1.5-pro",
    "gemini-pro",
    "gemini-1.0-pro"
]

def test_model(model_name):
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model_name}:generateContent?key={API_KEY}"
    print(f"\nTesting {model_name}...")
    print(f"URL: {url}")
    
    data = {
        "contents": [{
            "parts": [{"text": "Hello, this is a test."}]
        }]
    }
    
    try:
        response = requests.post(url, json=data, timeout=10)
        print(f"Status Code: {response.status_code}")
        if response.status_code == 200:
            print("SUCCESS!")
            print(response.json()['candidates'][0]['content']['parts'][0]['text'])
            return True
        else:
            print(f"FAILED: {response.text}")
            return False
    except Exception as e:
        print(f"EXCEPTION: {str(e)}")
        return False

def main():
    print("Starting Gemini Model Connectivity Test...")
    success = False
    for model in MODELS_TO_TEST:
        if test_model(model):
            success = True
            break
    
    if not success:
        print("\nALL MODELS FAILED.")
    else:
        print("\nAt least one model works.")

if __name__ == "__main__":
    main()
