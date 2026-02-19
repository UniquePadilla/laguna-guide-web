import sys
import json
import mysql.connector
import os
import requests
import re
import urllib.parse
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import time
 
#  ERROR PROTECTION + STABILITY
def get_robust_session():
    session = requests.Session()
    retry = Retry(
        total=5, # Increased retry count
        backoff_factor=2, # Longer backoff: 2s, 4s, 8s, 16s, 32s
        status_forcelist=[429, 500, 502, 503, 504],
    )
    adapter = HTTPAdapter(max_retries=retry)
    session.mount("https://", adapter)
    session.mount("http://", adapter)
    return session

ROBUST_SESSION = get_robust_session()

try:
    from rapidfuzz import fuzz
    HAS_RAPIDFUZZ = True
except ImportError:
    HAS_RAPIDFUZZ = False

# Database config
DB_CONFIG = {
    'user': 'if0_41199400',
    'password': 'uniqueken112',
    'host': 'sql113.infinityfree.com',
    'database': 'if0_41199400_tourist_guide_db',
    'port': 3306
}

GEMINI_API_KEY = "AIzaSyAJqootuQyeuuSLCCVoTcfjDDVLUWkEO3M"

#  INPUT INTELLIGENCE LAYER 

TAGALOG_MAP = { 
    "mag relax": "relax", "pahinga": "relax", "kainan": "restaurant",
    "kain": "eat", "masarap": "delicious", "saan": "where", 
    "ano": "what", "murang": "cheap", "mura": "cheap",
    "mahal": "expensive", "malapit": "near", "pasukan": "entrance fee",
    "bayad": "fee", "magkano": "how much", "oras": "open time",
    "pasyalan": "tourist spots", "pasyal": "visit", "lugar": "place",
    "maganda": "beautiful", "sikat": "popular", "pasikat": "popular",
    "yarn": "yan", "biyahe": "travel", "sakayan": "transport",
    "punta": "go", "paano": "how", "pwedeng": "can be", "pwede": "can"
}

SYNONYM_MAP = {
    "lodging": "hotel", "stay": "accommodation", "view": "scenery",
    "trekking": "hiking", "waterfall": "falls", "swimming": "resort",
    "chow": "food", "budget": "cheap", "fancy": "luxury"
}

def normalize_language(text): 
    text = text.lower()
    for tag, eng in TAGALOG_MAP.items(): 
        pattern = r'\b' + re.escape(tag) + r'\b'
        text = re.sub(pattern, eng, text)
    return text

def apply_synonyms(text):
    for word, syn in SYNONYM_MAP.items():
        pattern = r'\b' + re.escape(word) + r'\b'
        text = re.sub(pattern, syn, text)
    return text

# 2️⃣ SPELL CORRECTION PROMPT
MODELS_TO_TRY = [
    "gemini-2.0-flash",
    "gemini-flash-latest",
    "gemini-2.0-flash-lite"
]

def call_gemini_api(payload, api_key):
    last_error = None
    for model in MODELS_TO_TRY:
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={api_key}"
        try:
            # Increased timeout to 60 seconds to prevent ReadTimedOut errors
            r = ROBUST_SESSION.post(url, json=payload, timeout=60)
            if r.status_code == 404:
                last_error = f"Model {model} not found (404)"
                # print(f"DEBUG: Model {model} not found.", file=sys.stderr)
                continue
            if r.status_code == 429:
                last_error = "Resource exhausted (429)"
                # print(f"DEBUG: Model {model} 429.", file=sys.stderr)
                time.sleep(2) # Wait longer
                continue
                
            r.raise_for_status()
            return r.json()
        except Exception as e:
            last_error = str(e)
            # print(f"DEBUG: Model {model} failed: {e}", file=sys.stderr)
            time.sleep(1) 
            continue
    
    # If all failed, raise the last error but return None if it's 429/exhausted so we can fallback
    if "429" in str(last_error) or "Resource exhausted" in str(last_error):
        return None # Signal for fallback
        
    raise Exception(f"All Gemini models failed. Last error: {last_error}")

def correct_spelling(query, api_key): 
    if not api_key or api_key == "YOUR_API_KEY":
        api_key = GEMINI_API_KEY
    
    # Skip short queries to save API calls
    if len(query.split()) <= 3:
        return query

    if not api_key or api_key == "YOUR_API_KEY": 
        return query 

    prompt = f"""Correct the spelling of this sentence without changing its meaning. 
Return only the corrected sentence. 

Sentence: "{query}" """ 

    payload = { 
        "contents": [{"role": "user", "parts": [{"text": prompt}]}], 
        "generationConfig": {"temperature": 0.0, "maxOutputTokens": 60} 
    } 
    try: 
        result = call_gemini_api(payload, api_key)
        if result is None: return query # Fallback
        return result["candidates"][0]["content"]["parts"][0]["text"].strip() 
    except: 
        return query

def needs_clarification(query): 
    short_queries = ["where", "what", "how", "place", "help", "saan", "ano", "paano", "laguna"] 
    if len(query.split()) <= 1: 
        return True 
    for word in short_queries: 
        if query.strip().lower() == word: 
            return True 
    return False

# PERSISTENT CONVERSATION MEMORY 

def save_chat_message(session_id, role, message):
    conn = get_db_connection()
    if not conn: return
    try:
        cursor = conn.cursor()
        cursor.execute("INSERT INTO chat_history (session_id, role, message) VALUES (%s, %s, %s)", (session_id, role, message))
        conn.commit()
    finally:
        conn.close()

def get_chat_history(session_id, limit=6):
    conn = get_db_connection()
    if not conn: return []
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT role, message FROM chat_history WHERE session_id = %s ORDER BY timestamp DESC LIMIT %s", (session_id, limit))
        history = cursor.fetchall()
        formatted_history = []
        for h in reversed(history):
            formatted_history.append({"role": h['role'], "parts": [{"text": h['message']}]})
        return formatted_history
    finally:
        conn.close()

#  PERSONALIZATION MEMORY 

def update_user_preferences(session_id, intents):
    conn = get_db_connection()
    if not conn: return
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT budget, preference_tags FROM user_preferences WHERE session_id = %s", (session_id,))
        row = cursor.fetchone()
        current_budget = row['budget'] if row else None
        current_tags = row['preference_tags'].split(',') if row and row['preference_tags'] else []
        new_budget = current_budget
        if "budget" in intents: new_budget = "low"
        if "luxury" in intents: new_budget = "high"
        for intent in intents:
            if intent not in current_tags and intent not in ['general', 'budget', 'luxury']:
                current_tags.append(intent)
        tags_str = ",".join(current_tags[-10:])
        if row:
            cursor.execute("UPDATE user_preferences SET budget = %s, preference_tags = %s WHERE session_id = %s", (new_budget, tags_str, session_id))
        else:
            cursor.execute("INSERT INTO user_preferences (session_id, budget, preference_tags) VALUES (%s, %s, %s)", (session_id, new_budget, tags_str))
        conn.commit()
    finally:
        conn.close()

def get_user_preferences(session_id):
    conn = get_db_connection()
    if not conn: return None
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT budget, preference_tags FROM user_preferences WHERE session_id = %s", (session_id,))
        return cursor.fetchone()
    finally:
        conn.close()

#  SMART DATABASE SEARCH 

def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error:
        return None

def detect_intent(message: str): 
    message = message.lower() 
    intents = []
    if re.search(r"relax|chill|peaceful|rest|calm|pahinga|mag-relax|quiet|wellness|spa", message): intents.append("relaxation")
    if re.search(r"food|eat|restaurant|dish|hungry|delicious|cuisine|kain|kainan|masarap|pagkain|chow|dinner|lunch|breakfast", message): intents.append("food")
    if re.search(r"hotel|stay|accommodation|resort|lodge|sleep|check-in|tulugan|matutuluyan|transient|inn|room", message): intents.append("accommodation")
    if re.search(r"visit|spot|place|go|pasyalan|view|sight|landmark|destination|attraction|tour|trip|guide|map", message): intents.append("tourist_spot")
    if re.search(r"festival|event|celebration|party|perya|okasyon|concert|ganap", message): intents.append("events")
    if re.search(r"how to go|transport|commute|directions|bus|car|jeepney|sakayan|biyahe|punta|paano|route|traffic", message): intents.append("transport")
    if re.search(r"cheap|budget|affordable|low cost|free|mura|libre|tipid|sulit|price|magkano", message): intents.append("budget")
    if re.search(r"luxury|premium|exclusive|expensive|high end|mahal|sosyal|mayaman|fancy", message): intents.append("luxury")
    if re.search(r"family|kids|children|toddler|pamilya|anak|bata", message): intents.append("family-friendly")
    if re.search(r"couple|romantic|date|anniversary|mag-asawa|nobyo|love", message): intents.append("romantic")
    if re.search(r"adventure|thrill|extreme|hiking|climbing|bundok|talon|nature|outdoor|falls|camping|swim", message): intents.append("adventure")
    if re.search(r"how.*km|distance|how far|ilang km|gaano kalayo", message): intents.append("distance")
    return intents if intents else ["general"]

#  INTENT DETECTION PROMPT
def detect_intent_ai(query, api_key):
    # OPTIMIZATION: Check regex intents first
    regex_intents = detect_intent(query)
    if regex_intents and "general" not in regex_intents:
        return regex_intents[0] # Return the first detected intent

    if not api_key or api_key == "YOUR_API_KEY":
        api_key = GEMINI_API_KEY

    if not api_key or api_key == "YOUR_API_KEY":
        return "general"
    
    prompt = f"""Determine the user intent from this query. 
    Classify into one of these categories: 
    - relaxation 
    - food 
    - accommodation
    - tourist_spot
    - adventure 
    - events 
    - transport 
    - general 

    Return only the intent. 
    Query: "{query}" """

    payload = {"contents": [{"role": "user", "parts": [{"text": prompt}]}]}
    try:
        result = call_gemini_api(payload, api_key)
        if result is None: return regex_intents[0] # Fallback
        return result["candidates"][0]["content"]["parts"][0]["text"].strip().lower()
    except:
        return "general"

#  TOPIC DETECTION LAYER 
def detect_topic_keywords(user_input):
    tourism_keywords = [
        "laguna", "resort", "tourist", "travel",
        "restaurant", "food", "event", "festival",
        "transport", "hotel", "beach", "mountain",
        "swimming", "pool", "vacation", "staycation",
        "ride", "commute", "jeep", "bus", "falls",
        "lake", "hot spring", "spring", "resorts",
        "place", "spot", "visit", "go", "directions",
        "where", "recommend", "best", "top", "sikat",
        # Specific Laguna locations
        "calamba", "los banos", "uplb", "km", "distance",
        "sta rosa", "san pablo", "pagsanjan", "bay", "binan",
        "cavinti", "lumban", "nagcarlan", "sta cruz",
        # Major attractions
        "enchanted kingdom", "nuvali", "rizal shrine",
        "makiling", "hidden valley", "jamboree"
    ]

    for word in tourism_keywords:
        if word in user_input.lower():
            return "laguna_tourism"

    return "general"

def detect_topic_ai(user_input, api_key, history=[]):
    if not api_key or api_key == "YOUR_API_KEY":
        api_key = GEMINI_API_KEY

    # ENHANCED CONTEXT CHECK: Check last 2-3 messages for Laguna context
    if history and len(history) > 0:
        try:
            # Check last 2 messages (both user and bot)
            recent_messages = history[-2:] if len(history) >= 2 else history[-1:]
            context_text = ""
            
            for msg in recent_messages:
                if msg.get('role') in ['model', 'user']:
                    text = msg.get('parts', [{}])[0].get('text', '').lower()
                    context_text += " " + text
            
            # Strong Laguna indicators in recent conversation
            laguna_indicators = [
                'laguna', 'calamba', 'los banos', 'sta rosa', 'san pablo',
                'resort', 'hot spring', 'falls', 'pagsanjan', 'nuvali',
                'recommend', 'spot', 'tourist', 'relax', 'visit', 'trip',
                'accommodation', 'hotel', 'stay', 'overnight', 'vacation',
                'buko pie', 'enchanted kingdom', 'makiling', 'uplb'
            ]
            
            # If ANY Laguna indicator in recent messages, stay in Laguna mode
            for indicator in laguna_indicators:
                if indicator in context_text:
                    return "laguna_tourism"
                    
            # Special case: Short follow-ups after detailed bot response
            if len(user_input.split()) <= 3:  # Short query like "overnight", "yes", "how much"
                # If bot just gave a detailed response, assume continuation
                if len(history) > 0 and history[-1].get('role') == 'model':
                    last_bot_response = history[-1].get('parts', [{}])[0].get('text', '')
                    # If last bot response was long (>200 chars), assume we're in Laguna context
                    if len(last_bot_response) > 200:
                        return "laguna_tourism"
        except:
            pass

    # OPTIMIZATION: Try keyword detection first to save API calls
    keyword_topic = detect_topic_keywords(user_input)
    if keyword_topic == "laguna_tourism" or keyword_topic == "laguna": # detect_topic_keywords returns "laguna"
         return "laguna_tourism"

    if not api_key or api_key == "YOUR_API_KEY":
        return keyword_topic
    
    prompt = f"""
    Classify this query strictly into ONE of these categories:

    1. laguna_tourism
    2. general

    Query: "{user_input}"

    INSTRUCTIONS:
    - If the user asks about "tourist spots", "where to go", "food", "resorts", "swimming", "directions", etc., assume it is for LAGUNA, Philippines (category: laguna_tourism), UNLESS they explicitly mention another country/city (e.g. "Paris", "Japan", "Manila").
    - If the query is clearly about emotions, greetings, or social interaction (e.g. "I love you", "how are you", "thank you", "good morning", "you're amazing"), select "general" - these are NOT tourism queries.
    - If the query is clearly unrelated to tourism/travel (e.g. "math help", "who is ...", "coding", "emotional support", "science questions"), select "general".
    - For truly ambiguous queries like "yes", "sure", "help" with no context, check conversation history for tourism context before defaulting to laguna_tourism.


    Only return the category name.
    """
    
    try:
        payload = {"contents": [{"role": "user", "parts": [{"text": prompt}]}]}
        result = call_gemini_api(payload, api_key)
        if result is None: return keyword_topic # Fallback
        topic = result["candidates"][0]["content"]["parts"][0]["text"].strip().lower()
        if "laguna" in topic: return "laguna_tourism"
        return "general"
    except:
        return detect_topic_keywords(user_input)

def search_db(query, intents=[], session_id="default"):
    conn = get_db_connection()
    if not conn: return []
    try:
        cursor = conn.cursor(dictionary=True)
        
        # print(f"DEBUG: Search query: {query}, Intents: {intents}", file=sys.stderr)

        # Smart Search Logic
        is_generic_top_query = re.search(r"top|best|popular|famous|sikat|maganda|recommend|spots|places|where to go|guide|trip|tour", query.lower()) and len(query.split()) < 10
        
        sql = "SELECT * FROM spots WHERE 1=1"
        params = []

        # CLEAN KEYWORDS: Remove words that triggered intent to prevent over-filtering
        # e.g. "nature trip" -> "nature" maps to adventure, "trip" maps to tourist_spot.
        # If we filter by name LIKE '%nature%' it might fail.
        
        stopwords = ["a", "an", "the", "is", "are", "in", "at", "on", "what", "where", "ang", "ng", "sa", "mga"]
        
        clean_query = re.sub(r'[^\w\s]', '', query.lower())
        keywords = [w for w in clean_query.split() if w not in stopwords]
        
        if is_generic_top_query and not keywords:
             # Return ALL top featured spots
             sql += " AND featured = 1"
        else:
            if keywords:
                keyword_conditions = []
                for kw in keywords:
                    keyword_conditions.append("(name LIKE %s OR description LIKE %s OR location LIKE %s OR category LIKE %s OR type LIKE %s)")
                    term = f"%{kw}%"
                    params.extend([term, term, term, term, term])
                
                sql += " AND (" + " OR ".join(keyword_conditions) + ")"

        # Intent mapping to 'spots' columns
        intent_map = {
            'budget': ("entranceFee < 100 OR entranceFee = 'Free' OR entranceFee LIKE %s", ["%Free%"]),
            'luxury': ("entranceFee > 500", []),
            'family-friendly': ("description LIKE %s OR description LIKE %s OR highlights LIKE %s", ["%family%", "%kids%", "%family%"]),
            'romantic': ("description LIKE %s OR description LIKE %s", ["%romantic%", "%couple%"]),
            'adventure': ("category LIKE %s OR category LIKE %s OR category LIKE %s OR type='destination'", ["%hiking%", "%falls%", "%nature%"]),
            'food': ("type = 'cuisine' OR category LIKE %s OR category LIKE %s", ["%restaurant%", "%cafe%"]),
            'accommodation': ("type = 'accommodation' OR category LIKE %s OR category LIKE %s OR category LIKE %s", ["%hotel%", "%resort%", "%inn%"]),
            'tourist_spot': ("type = 'destination' OR type = 'cultural' OR category LIKE %s OR category LIKE %s OR category LIKE %s", ["%park%", "%shrine%", "%landmark%"]),
            'relaxation': ("type = 'destination' OR category LIKE %s OR category LIKE %s", ["%resort%", "%park%"]),
            'events': ("category LIKE %s OR category LIKE %s", ["%festival%", "%event%"]),
            'transport': ("description LIKE %s OR description LIKE %s", ["%bus%", "%jeep%"]),
        }

        active_filters = list(intents)
        


        prefs = get_user_preferences(session_id)
        # Apply user preferences only if query is generic
        if not intents or (len(intents) == 1 and intents[0] == "general"):
            if prefs and prefs['budget']: active_filters.append('budget' if prefs['budget'] == 'low' else 'luxury')
            if prefs and prefs['preference_tags']: active_filters.extend(prefs['preference_tags'].split(','))
        
        for intent in set(active_filters):
            if intent in intent_map: 
                condition, new_params = intent_map[intent]
                sql += f" AND ({condition})"
                params.extend(new_params)
        
        sql += " ORDER BY featured DESC LIMIT 10"
        
        # print(f"DEBUG: SQL: {sql}", file=sys.stderr)
        # print(f"DEBUG: Params: {params}", file=sys.stderr)
        
        cursor.execute(sql, params)
        results = cursor.fetchall()
        
        # FALLBACK: If "top spots" query returned 0 results (maybe no featured spots), try generic limit
        if not results and is_generic_top_query:
             fallback_sql = "SELECT * FROM spots LIMIT 5"
             cursor.execute(fallback_sql)
             results = cursor.fetchall()

        scored = [] 
        for row in results: 
            combined_text = f"{row['name']} {row['description']} {row['location']} {row['type']} {row['category']}" 
            if HAS_RAPIDFUZZ: score = fuzz.partial_ratio(query.lower(), combined_text.lower()) 
            else: score = 100 if query.lower() in combined_text.lower() else 50
            if row.get('featured'): score += 10
            if score > 40: scored.append((score, row)) 
        scored.sort(key=lambda x: x[0], reverse=True) 
        return [row for score, row in scored[:5]]
    finally:
        conn.close()

#  CONFIDENCE SCORING SYSTEM 
def is_weak_response(text):
    weak_phrases = [
        "i'm not sure", "i'm sorry", "i do not know", 
        "cannot find", "no information", "as an ai",
        "hindi ko alam", "pasensya na", "walang impormasyon",
        "not sure", "let's explore", "unsure"
    ]
    text = text.lower()
    return any(phrase in text for phrase in weak_phrases) or len(text) < 50

def is_small_talk(text):
    allowed = {
        "hi", "hello", "hey", "yo", "there", "good", "morning", "afternoon", "evening",
        "thanks", "thank", "you", "u", "bye", "goodbye", "ok", "okay", "sure", "yes",
        "no", "please", "welcome", "how", "are", "whats", "what's", "up"
    }
    q = re.sub(r"[^a-z\s']", " ", text.lower()).strip()
    if not q:
        return False
    if re.search(r"\b(how are you|how r u|what's up|whats up)\b", q):
        return True
    tokens = [t for t in q.split() if t]
    return len(tokens) <= 6 and all(t in allowed for t in tokens)

def is_site_question(text):
    t = text.lower()
    if re.search(r"\bwho\s+(are\s+you|you|is\s+this|is\s+the\s+assistant)\b", t):
        return True
    if re.search(r"\bwhat\s+is\s+your\s+name\b", t):
        return True
    return re.search(r"(what.*can.*(do|offer)|features|capabilities|function|what.*is.*this.*system|what.*is.*this.*website|what.*does.*this.*website|purpose.*of.*this.*website|about.*this.*website|what.*this.*website.*for)", t) is not None

def is_system_error(text):
    if not text:
        return False
    t = text.lower()
    return "ai system not fully configured" in t or "gemini error" in t

def is_weak_response_for_query(text, user_input):
    if not text:
        return True
    t = text.lower()
    weak_phrases = [
        "i'm not sure", "i'm sorry", "i do not know", 
        "cannot find", "no information", "as an ai",
        "hindi ko alam", "pasensya na", "walang impormasyon",
        "not sure", "let's explore", "unsure"
    ]
    if any(phrase in t for phrase in weak_phrases):
        return True
    if len(user_input.split()) <= 4:
        return False
    return len(text) < 50

def evaluate_confidence(answer, db_results, web_result): 
    score = 0 
    if db_results: score += 40 
    if web_result: score += 20 
    if len(answer) > 150: score += 20 
    if is_weak_response(answer): score -= 50 
    return score

#  MULTI-SOURCE INTELLIGENCE MERGE (Web Tools) 
try:
    from ddgs import DDGS
    HAS_DDGS = True
except ImportError:
    try:
        from duckduckgo_search import DDGS
        HAS_DDGS = True
    except ImportError:
        HAS_DDGS = False

def duckduckgo_search(query):
    results_text = ""
    try:
        if HAS_DDGS:
            # Use 'us-en' for broad English coverage
            with DDGS() as ddgs:
                results = list(ddgs.text(query, region='us-en', max_results=3))
                if results:
                    for r in results:
                        results_text += f"{r['title']}\n{r['body']}\n\n"
                    return results_text
    except:
        pass
    
    # Fallback to API if DDGS fails or not installed
    try:
        url = "https://api.duckduckgo.com/?q=" + urllib.parse.quote(query) + "&format=json&no_html=1&skip_disambig=1"
        response = ROBUST_SESSION.get(url, timeout=10)
        if response.status_code == 200:
            data = response.json()
            return data.get('AbstractText') or (data.get('RelatedTopics')[0]['Text'] if data.get('RelatedTopics') else None)
    except: return None
    return None

def wikipedia_summary(query):
    try:
        search_url = "https://en.wikipedia.org/w/api.php?action=opensearch&search=" + urllib.parse.quote(query) + "&limit=1&namespace=0&format=json"
        res = ROBUST_SESSION.get(search_url, timeout=10)
        if res.status_code != 200: return None
        data = res.json()
        if not data or not data[1]: return None
        title = data[1][0]
        sum_url = "https://en.wikipedia.org/api/rest_v1/page/summary/" + urllib.parse.quote(title)
        res2 = ROBUST_SESSION.get(sum_url, timeout=10)
        if res2.status_code != 200: return None
        data2 = res2.json()
        return f"{data2['title']}: {data2['extract']}" if 'extract' in data2 else None
    except: return None

def handle_distance_query(user_input, intents):
    """
    Special handler for distance/route queries
    Example: "how km calamba to uplb", "distance from sta rosa to san pablo"
    Returns detailed distance and route information
    """
    query_lower = user_input.lower()
    
    # Common Laguna locations and their relationships
    laguna_locations = {
        "calamba": {
            "nearby": ["uplb", "los banos", "sta rosa", "bay"],
            "description": "City in Laguna, birthplace of Jose Rizal, known for hot springs",
            "attractions": ["Rizal Shrine", "Hot springs resorts", "SM Calamba"]
        },
        "uplb": {
            "nearby": ["calamba", "los banos", "bay"],
            "description": "University of the Philippines Los Baños campus",
            "attractions": ["UPLB Museum", "Botanical Garden", "Makiling Forest"]
        },
        "los banos": {
            "nearby": ["calamba", "uplb", "bay"],
            "description": "Municipality known for hot springs and UPLB",
            "attractions": ["UPLB", "Mt. Makiling", "Hot springs", "Jamboree Lake"]
        },
        "sta rosa": {
            "nearby": ["calamba", "binan", "cabuyao"],
            "description": "City with major commercial centers",
            "attractions": ["Enchanted Kingdom", "Nuvali", "Paseo de Sta Rosa"]
        },
        "san pablo": {
            "nearby": ["nagcarlan", "alaminos", "tiaong"],
            "description": "City of Seven Lakes, known for buko pie",
            "attractions": ["Seven Lakes", "Sampaloc Lake", "Buko pie shops"]
        },
        "pagsanjan": {
            "nearby": ["lumban", "cavinti", "sta cruz"],
            "description": "Famous for Pagsanjan Falls boat adventure",
            "attractions": ["Pagsanjan Falls", "Magdapio Falls"]
        },
        "binan": {
            "nearby": ["sta rosa", "calamba", "san pedro"],
            "description": "City with commercial and residential areas",
            "attractions": ["Biñan Church", "Commercial centers"]
        },
        "bay": {
            "nearby": ["los banos", "calamba", "calauan"],
            "description": "Coastal town on Laguna de Bay",
            "attractions": ["Laguna de Bay shoreline", "Local festivals"]
        },
    }
    
    # Extract location names from query
    locations_found = []
    for loc in laguna_locations.keys():
        if loc in query_lower:
            locations_found.append(loc)
    
    # Handle two locations (distance between A and B)
    if len(locations_found) >= 2:
        loc1, loc2 = locations_found[0], locations_found[1]
        info1 = laguna_locations.get(loc1, {})
        info2 = laguna_locations.get(loc2, {})
        
        response = f"""The distance between **{loc1.title()}** and **{loc2.title()}** in Laguna:

📍 **{loc1.title()}**: {info1.get('description', 'A place in Laguna')}
📍 **{loc2.title()}**: {info2.get('description', 'A place in Laguna')}

🚗 **Estimated Distance**: 10-25 kilometers (varies by route)
⏱️ **Travel Time**: 
   • By car: 20-40 minutes depending on traffic
   • By jeepney/bus: 30-60 minutes with stops

🚌 **Transport Options**:
   • Jeepney routes available between major towns (₱20-50)
   • Tricycle for short distances (₱20-100)
   • UV Express vans for direct routes (₱50-100)
   • Private vehicle via main highways

💡 **Travel Tips**:
   • Travel early morning (6-8 AM) to avoid traffic
   • Main route: Via South Luzon Expressway or national highway
   • Ask locals for the fastest current route
   • Consider traffic on weekdays vs weekends

📍 **Things to do in {loc1.title()}**: {', '.join(info1.get('attractions', ['Various spots']))}
📍 **Things to do in {loc2.title()}**: {', '.join(info2.get('attractions', ['Various spots']))}

Would you like to know more about places to visit in {loc1.title()} or {loc2.title()}?"""
        
        return response
    
    # Handle one location mentioned
    elif len(locations_found) == 1:
        loc = locations_found[0]
        info = laguna_locations.get(loc, {})
        nearby = info.get('nearby', [])
        attractions = info.get('attractions', [])
        
        response = f"""I noticed you're asking about **{loc.title()}** in Laguna!

📍 **About {loc.title()}**: {info.get('description', 'A place in Laguna')}

🎯 **Top attractions**:
{chr(10).join([f'   • {attr}' for attr in attractions])}

🗺️ **Nearby places** (10-30 minutes away):
{chr(10).join([f'   • {n.title()}' for n in nearby])}

For specific distance and directions:
• Let me know where you're traveling FROM
• I can help with transport options and routes
• Google Maps has real-time traffic updates for exact distances

What would you like to know about {loc.title()} or nearby places?"""
        
        return response
    
    # Fallback: No specific locations detected
    response = """I can help you with distances and routes in Laguna! 

To give you accurate information, please specify:
• **Starting point** (e.g., "from Calamba", "from Manila")
• **Destination** (e.g., "to UPLB", "to Pagsanjan Falls")

📍 **Popular Laguna destinations and approximate distances from Calamba**:
   • UPLB/Los Baños - 10-15 km (20-30 mins)
   • Sta. Rosa - 15-20 km (25-35 mins)
   • San Pablo - 30-40 km (45-60 mins)
   • Pagsanjan Falls - 35-45 km (50-70 mins)
   • Bay - 15-20 km (25-35 mins)

🚗 **General travel times in Laguna**:
   • Most towns are 15-45 minutes apart by car
   • Add 10-20 minutes during rush hour
   • Jeepneys are slower but more affordable

Would you like recommendations for any of these places?"""
    
    return response

def handle_unknown_place(place_name, query):
    """
    Handle queries about small/unknown Laguna places
    Uses logical inference and nearby attractions
    """
    place_lower = place_name.lower()
    
    # Map of all Laguna municipalities (even tiny ones)
    laguna_towns = {
        # Major cities/municipalities
        'calamba': {'type': 'city', 'nearby': ['los banos', 'sta rosa'], 'known_for': 'Rizal birthplace, hot springs'},
        'sta rosa': {'type': 'city', 'nearby': ['calamba', 'binan'], 'known_for': 'Enchanted Kingdom, Nuvali'},
        'san pablo': {'type': 'city', 'nearby': ['nagcarlan', 'alaminos'], 'known_for': 'Seven Lakes, buko pie'},
        'binan': {'type': 'city', 'nearby': ['sta rosa', 'san pedro'], 'known_for': 'residential, commercial'},
        'san pedro': {'type': 'city', 'nearby': ['binan', 'muntinlupa'], 'known_for': 'Metro Manila border'},
        'cabuyao': {'type': 'city', 'nearby': ['sta rosa', 'calamba'], 'known_for': 'industrial, Evia mall'},
        
        # Municipalities  
        'los banos': {'type': 'municipality', 'nearby': ['calamba', 'bay'], 'known_for': 'UPLB, Mt. Makiling, hot springs'},
        'bay': {'type': 'municipality', 'nearby': ['los banos', 'calauan'], 'known_for': 'Laguna de Bay shoreline'},
        'calauan': {'type': 'municipality', 'nearby': ['bay', 'san pablo'], 'known_for': 'Hidden Valley Springs'},
        'nagcarlan': {'type': 'municipality', 'nearby': ['san pablo', 'liliw'], 'known_for': 'Underground Cemetery'},
        'liliw': {'type': 'municipality', 'nearby': ['nagcarlan', 'majayjay'], 'known_for': 'tsinelas (sandal) capital'},
        'majayjay': {'type': 'municipality', 'nearby': ['liliw', 'lucban'], 'known_for': 'Taytay Falls, Baroque church'},
        'pagsanjan': {'type': 'municipality', 'nearby': ['sta cruz', 'lumban'], 'known_for': 'Pagsanjan Falls'},
        'lumban': {'type': 'municipality', 'nearby': ['pagsanjan', 'cavinti'], 'known_for': 'embroidery'},
        'cavinti': {'type': 'municipality', 'nearby': ['lumban', 'pagsanjan'], 'known_for': 'Caliraya Lake, underground river'},
        'paete': {'type': 'municipality', 'nearby': ['pakil', 'kalayaan'], 'known_for': 'woodcarving'},
        'pakil': {'type': 'municipality', 'nearby': ['paete', 'pangil'], 'known_for': 'Turumba Festival'},
        'pangil': {'type': 'municipality', 'nearby': ['pakil', 'siniloan'], 'known_for': 'embroidery, woodcarving'},
        'siniloan': {'type': 'municipality', 'nearby': ['pangil', 'famy'], 'known_for': 'Buruwisan Falls'},
        'santa cruz': {'type': 'municipality', 'nearby': ['pagsanjan', 'pila'], 'known_for': 'capital of Laguna'},
        'pila': {'type': 'municipality', 'nearby': ['santa cruz', 'victoria'], 'known_for': 'heritage town'},
        'victoria': {'type': 'municipality', 'nearby': ['pila', 'san pablo'], 'known_for': 'fruit farms'},
        'alaminos': {'type': 'municipality', 'nearby': ['san pablo', 'bay'], 'known_for': 'Nagbalon Falls'},
        'magdalena': {'type': 'municipality', 'nearby': ['majayjay', 'paete'], 'known_for': 'coconut plantations'},
        'mabitac': {'type': 'municipality', 'nearby': ['santa maria', 'famy'], 'known_for': 'rural farming'},
        'famy': {'type': 'municipality', 'nearby': ['mabitac', 'siniloan'], 'known_for': 'rice terraces'},
        'santa maria': {'type': 'municipality', 'nearby': ['mabitac', 'kalayaan'], 'known_for': 'hot springs'},
        'kalayaan': {'type': 'municipality', 'nearby': ['paete', 'santa maria'], 'known_for': 'farming community'},
        'luisiana': {'type': 'municipality', 'nearby': ['los banos', 'majayjay'], 'known_for': 'Hulugan Falls'},
        'rizal': {'type': 'municipality', 'nearby': ['nagcarlan', 'mabitac'], 'known_for': 'fishing, lakeside'},
    }
    
    # Check if place is known
    for town_key, info in laguna_towns.items():
        if town_key in place_lower:
            nearby_list = ', '.join([n.title() for n in info['nearby']])
            return f"""**{place_name.title()}** is a {info['type']} in Laguna!

📍 **What it's known for**: {info['known_for']}

🗺️ **Nearby attractions** (easy to combine in one trip):
{chr(10).join([f'   • {n.title()}' for n in info['nearby']])}

🚌 **Getting there**: Take jeepney or bus to nearby {info['nearby'][0].title()}, then local tricycle to {place_name.title()}

💡 **Tips**:
• Small Laguna towns have authentic local life and cheaper prices
• Best visited on weekdays when less crowded
• Ask locals for hidden gems - they love helping visitors!
• Most have old Spanish churches worth visiting

Would you like specific recommendations for things to do in {place_name.title()} or nearby areas?"""
    
    # Truly unknown place - give helpful general response
    return f"""I'd love to tell you more about **{place_name}**!

While I don't have specific details about this place in my database, here's what I can share:

If {place_name} is in Laguna:
• It's likely a small barangay or subdivision within a larger municipality
• Laguna has 24 municipalities and 6 cities total
• Even small areas have local charm - old churches, food spots, nature

**To help you better, could you tell me**:
• What are you looking for? (food, accommodation, attractions)
• Which larger town is it near? (Calamba, San Pablo, Los Baños, etc.)
• Are you asking about distance/directions?

**Meanwhile, here are popular Laguna destinations nearby any location**:
• Pagsanjan Falls - thrilling boat ride
• Enchanted Kingdom - theme park fun
• Hidden Valley Springs - relaxing hot springs
• Mt. Makiling - hiking and nature

Let me know what you're interested in and I'll give you the best recommendations!"""


def should_use_unknown_place_handler(query, db_results):
    """Check if query mentions a place but database has no results"""
    # If DB has results, don't use fallback
    if db_results and len(db_results) > 0:
        return False, None
    
    # Check if query mentions a Laguna place
    query_lower = query.lower()
    
    # List of Laguna place names
    laguna_keywords = [
        'calamba', 'sta rosa', 'san pablo', 'binan', 'san pedro', 'cabuyao',
        'los banos', 'uplb', 'bay', 'calauan', 'nagcarlan', 'liliw', 'majayjay',
        'pagsanjan', 'lumban', 'cavinti', 'paete', 'pakil', 'pangil', 'siniloan',
        'santa cruz', 'pila', 'victoria', 'alaminos', 'magdalena', 'mabitac',
        'famy', 'santa maria', 'kalayaan', 'luisiana', 'rizal'
    ]
    
    for place in laguna_keywords:
        if place in query_lower:
            return True, place
    
    return False, None

def handle_short_followup(user_input, history, intents):
    """
    Handle short follow-up queries like "overnight", "yes", "how much"
    by inferring intent from conversation history
    """
    if not history or len(history) < 1:
        return None
    
    query_lower = user_input.lower().strip()
    
    # Get last bot message for context
    last_bot_msg = ""
    for msg in reversed(history):
        if msg.get('role') == 'model':
            last_bot_msg = msg.get('parts', [{}])[0].get('text', '').lower()
            break
    
    # CASE 1: User says "overnight" after relaxation/accommodation discussion
    if query_lower in ['overnight', 'overnight stay', 'stay overnight', 'sleepover']:
        if any(word in last_bot_msg for word in ['relax', 'resort', 'hotel', 'spring', 'accommodation', 'stay']):
            return {
                'inferred_intent': 'accommodation',
                'refined_query': 'overnight accommodation resort hotel laguna',
                'context': 'User wants overnight accommodation options in Laguna'
            }
    
    # CASE 2: User says "yes" or "sure" after bot asked a question
    if query_lower in ['yes', 'yeah', 'yep', 'sure', 'okay', 'ok', 'oo', 'sige']:
        # Check if bot asked about overnight
        if 'overnight' in last_bot_msg or 'stay' in last_bot_msg:
            return {
                'inferred_intent': 'accommodation',
                'refined_query': 'overnight resort hotel accommodation laguna',
                'context': 'User confirmed interest in overnight stay'
            }
        # Check if bot asked about food
        if 'eat' in last_bot_msg or 'food' in last_bot_msg or 'restaurant' in last_bot_msg:
            return {
                'inferred_intent': 'food',
                'refined_query': 'restaurant food dining laguna',
                'context': 'User confirmed interest in food/dining'
            }
    
    # CASE 3: User asks "how much" or "price"
    if any(word in query_lower for word in ['how much', 'price', 'cost', 'magkano', 'bayad']):
        # Extract what they're asking about from last bot message
        if 'resort' in last_bot_msg or 'hotel' in last_bot_msg:
            return {
                'inferred_intent': 'accommodation',
                'refined_query': 'resort hotel price accommodation laguna',
                'context': 'User asking about accommodation prices'
            }
        if 'falls' in last_bot_msg or 'entrance' in last_bot_msg:
            return {
                'inferred_intent': 'tourist_spot',
                'refined_query': 'entrance fee attraction price laguna',
                'context': 'User asking about entrance fees'
            }
    
    # CASE 4: User gives location preference
    location_words = ['calamba', 'los banos', 'sta rosa', 'san pablo', 'bay', 'pagsanjan']
    for loc in location_words:
        if loc in query_lower:
            return {
                'inferred_intent': 'location_specific',
                'refined_query': f'{loc} {last_bot_msg[:50]}',  # Combine with previous context
                'context': f'User specified location: {loc}'
            }
    
    return None

#  FOLLOW-UP QUESTION PROMPT
def generate_follow_up_ai(query, intent, api_key, history_context=""):
    if not api_key or api_key == "YOUR_API_KEY":
        api_key = GEMINI_API_KEY

    if not api_key or api_key == "YOUR_API_KEY":
        return "Is there anything specific you'd like to know more about these places?"
    
    prompt = f"""Generate a short follow-up question to keep conversation going: 
Based on the user query "{query}" and detected intent "{intent}", 
create a friendly follow-up question that helps narrow down recommendations or preferences. 
Context from previous messages: {history_context}
Do not repeat previous information."""

    payload = {"contents": [{"role": "user", "parts": [{"text": prompt}]}]}
    try:
        result = call_gemini_api(payload, api_key)
        if result is None: return "Is there anything specific you'd like to know more about these places?"
        return result["candidates"][0]["content"]["parts"][0]["text"].strip()
    except:
        return "Is there anything specific you'd like to know more about these places?"

#  GEMINI BRAIN 
def ask_gemini(query, system_prompt, api_key, history=[]):
    # Fallback to hardcoded key if not provided
    if not api_key or api_key == "YOUR_API_KEY":
        api_key = GEMINI_API_KEY

    if not api_key or api_key == "YOUR_API_KEY":
        return "AI system not fully configured. Please check your API key."
    
    # DEBUG: Print user input to verify Gemini is being called
    # Using stderr to avoid breaking JSON output for PHP
    print(f"Calling Gemini with: {query}", file=sys.stderr)

    # Ensure history is in correct format for Gemini
    formatted_history = []
    for item in history:
        if isinstance(item, dict) and 'role' in item and 'parts' in item:
            formatted_history.append(item)
    
    payload = {
        "systemInstruction": {"parts": [{"text": system_prompt}]},
        "contents": formatted_history + [{"role": "user", "parts": [{"text": query}]}],
        "generationConfig": {
            "temperature": 0.7, 
            "topP": 0.9, 
            "maxOutputTokens": 1500,
            "stopSequences": ["I'm not sure", "I don't know", "let me explore"]
        }
    }
    try:
        result = call_gemini_api(payload, api_key)
        
        if result is None:
            return None # Fallback signal
            
        if "candidates" in result:
            return result["candidates"][0]["content"]["parts"][0]["text"]
        else:
             return f"Gemini Error: No candidates returned. Response: {json.dumps(result)}"

    except Exception as e:
        return f"Gemini Error: {str(e)}"

def general_mode(user_input, api_key, history=[], google_key=None, google_cx=None):
    prompt = f"""
    You are Doquerainee AI.

    Answer the user naturally, intelligently, and confidently.
    You are not limited to tourism. You can answer ANY topic (history, science, math, coding, life, etc.).
    Be conversational and human-like.

    User: {user_input}
    """
    
    small_talk = is_small_talk(user_input)
    if is_site_question(user_input):
        return "I'm **Doquerainee**, the assistant for the **Laguna Tourist Guide System**. This site is focused only on **Laguna, Philippines**—ask about Laguna spots, food, events, directions, or how to use the site."
    is_laguna = detect_topic_keywords(user_input) == "laguna"
    if not is_laguna and not small_talk:
        return "I focus on **Laguna, Philippines** only. Ask about Laguna tourist spots, food, events, directions, or site features."

    first_answer = ask_gemini(user_input, prompt, api_key, history)
    
    # Fallback: If AI is down, try web search directly
    if first_answer is None or is_system_error(first_answer):
         if small_talk:
             return "Hi! How can I help you today?"
         web_results = ""
         if google_key and google_cx:
             web_results = google_search(user_input, google_key, google_cx)
         
         if not web_results:
             web_results = duckduckgo_search(user_input)

         if web_results:
             return f"Here are a few quick results I found:\n\n{web_results}"
         return "I can help with general questions or Laguna travel tips. What would you like to know?"
    
    if small_talk:
        return first_answer

    if not is_weak_response_for_query(first_answer, user_input):
        return first_answer
        
    # Smart Retry for weak responses
    web_results = ""
    if google_key and google_cx:
         web_results = google_search(user_input, google_key, google_cx)
    
    if not web_results:
        web_results = duckduckgo_search(user_input)
    
    enhanced_prompt = f"""
    The user asked: {user_input}

    Your first answer was weak.

    Here is web information:
    {web_results}

    Create a strong, confident, well-structured answer.
    Do not say you are unsure.
    """
    
    result = ask_gemini(user_input, enhanced_prompt, api_key, history)
    if result is None: return first_answer
    return result

def fallback_laguna_response(query, db_results, web_info=None):
    msg = "Here are solid recommendations for you:"
    
    # If we have web info, prioritize showing that if DB is empty
    if not db_results and web_info:
        return f"Here’s what I found that matches your request:\n\n{web_info}", []

    # If no results, try to find popular ones
    if not db_results:
        msg = "Here are some popular spots in Laguna you might enjoy:"
        # Try to fetch some random featured spots if specific search failed
        db_results = search_db("top spots")
        
        # If still no results (no featured spots), just get random ones
        if not db_results:
            conn = get_db_connection()
            if conn:
                try:
                    cursor = conn.cursor(dictionary=True)
                    cursor.execute("SELECT * FROM spots ORDER BY RAND() LIMIT 5")
                    db_results = cursor.fetchall()
                except:
                    pass
                finally:
                    conn.close()

    # Format the message nicely
    if db_results:
        msg += "\n\n"
        for i, spot in enumerate(db_results[:5], 1):
            msg += f"{i}. **{spot['name']}** ({spot['category']})\n"
            msg += f"   Location: {spot['location']}\n"
            if spot.get('description'):
                desc = spot['description'][:100] + "..." if len(spot['description']) > 100 else spot['description']
                msg += f"   Note: {desc}\n"
            msg += "\n"
    else:
        msg = "I'm unable to access the database at the moment. Please check your connection or try again later."
    
    return msg, db_results

def google_search(query, api_key=None, cx=None):
    if not api_key or not cx: return None
    try:
        url = "https://www.googleapis.com/customsearch/v1"
        params = {
            'q': query,
            'key': api_key,
            'cx': cx,
            'num': 3
        }
        resp = ROBUST_SESSION.get(url, params=params, timeout=10)
        if resp.status_code == 200:
            data = resp.json()
            if 'items' in data:
                snippets = []
                for item in data['items'][:3]:
                    snippets.append(f"{item.get('title', '')}: {item.get('snippet', '')}")
                return "\n\n".join(snippets)
    except:
        pass
    return None

def laguna_mode(user_input, query, api_key, session_id, history=[], google_key=None, google_cx=None):
    # Intent detection 
    # Use original user_input for better AI context understanding
    primary_intent = detect_intent_ai(user_input, api_key)
    intents = detect_intent(query)
    if primary_intent not in intents: intents.append(primary_intent)
    
    # Handle short follow-ups intelligently
    if len(user_input.split()) <= 3:  # Short query
        followup_result = handle_short_followup(user_input, history, intents)
        if followup_result:
            # Override query and intents with refined versions
            query = followup_result['refined_query']
            if followup_result['inferred_intent'] not in intents:
                intents.append(followup_result['inferred_intent'])
            # Continue with refined query instead of original

    # Special handler for distance/route queries
    if "distance" in intents:
        distance_response = handle_distance_query(user_input, intents)
        print(json.dumps({
            "response": distance_response,
            "results": [],
            "intents": intents,
            "follow_up": "Would you like to know more about places to visit along the way?"
        }))
        return
    
    if needs_clarification(query): 
        print(json.dumps({ 
            "response": f"Could you tell me more about what you're looking for regarding {query}? (e.g., cheap resorts, food, hiking)",
            "follow_up": "What kind of experience do you prefer?",
            "results": [],
            "intents": intents
        })) 
        return

    update_user_preferences(session_id, intents)
    
    # 1. DATABASE SEARCH
    # print("DEBUG: Searching DB...", file=sys.stderr)
    db_results = search_db(query, intents, session_id)
    # print(f"DEBUG: Found {len(db_results)} results.", file=sys.stderr)
    
    # 1.5 UNKNOWN PLACE HANDLER - if DB empty but query mentions a Laguna place
    use_fallback, place_name = should_use_unknown_place_handler(query, db_results)
    if use_fallback and place_name:
        fallback_response = handle_unknown_place(place_name, query)
        print(json.dumps({
            "response": fallback_response,
            "results": [],
            "intents": intents,
            "follow_up": f"What else would you like to know about {place_name.title()} or Laguna?"
        }))
        return

    # 2. WEB SEARCH (if DB is empty or AI needs more context)
    web_info = ""
    if not db_results:
        # SMART WEB SEARCH: Add Laguna context for better results
        # Only search if query has enough context
        if len(user_input.split()) >= 2 or any(word in user_input.lower() for word in ['where', 'what', 'how', 'when']):
            search_query = f"Laguna Philippines {user_input}"
            
            # Add intent-specific keywords for better results
            if "accommodation" in intents or "hotel" in query.lower():
                search_query += " resort hotel accommodation"
            elif "food" in intents:
                search_query += " restaurant food dining"
            elif "tourist_spot" in intents:
                search_query += " tourist attraction destination"
            
            # Try Google Search if keys are available
            if google_key and google_cx:
                web_info = google_search(search_query, google_key, google_cx)
        
        # Fallback to DDG/Wiki if Google fails or keys missing
        if not web_info:
            web_info = duckduckgo_search(f"Laguna Philippines {user_input}") or wikipedia_summary(f"Laguna {user_input}")
    
    # 3. AI GENERATION
    context_str = "\n".join([f"- {r['name']}: {r['description']} ({r['location']})" for r in db_results])
    
    system_prompt = f"""
    You are Doquerainee, a friendly and enthusiastic Laguna Tourism Expert. 
    
    DATABASE RESULTS:
    {context_str}

    WEB INFORMATION:
    {web_info}
    
    BACKUP KNOWLEDGE (Use when database/web is empty):
    
    MAJOR ATTRACTIONS:
    - Enchanted Kingdom (Sta. Rosa): Theme park, amusement rides, family-friendly, entrance ₱800-1000
    - Rizal Shrine (Calamba): Jose Rizal's birthplace museum, historical site, entrance ₱20
    - Pagsanjan Falls (Pagsanjan): Boat ride through rapids to waterfall, adventure activity, ₱500-800
    - Nuvali (Sta. Rosa): Parks, wildlife sanctuary, bike trails, restaurants, free entry
    - Seven Lakes (San Pablo): Natural crater lakes, boating, swimming, scenic views
    - Mt. Makiling (Los Baños): Hiking trails, hot springs, forest, UPLB campus nearby
    - Hidden Valley Springs (Calauan): Natural hot springs resort, day tour ₱1500-2000
    - UPLB Campus (Los Baños): University, museums, botanical garden, Makiling forest
    
    OVERNIGHT ACCOMMODATION OPTIONS:
    
    BUDGET RESORTS (₱1,000-2,500/night):
    - Private Pool Resorts (Calamba/Los Baños): ₱3,000-5,000 for private pool, good for groups
    - Pansol Area (Calamba): Hundreds of options, ₱1,500-3,000/night per cottage
    - Barangay Hot Spring Resorts (Los Baños): ₱800-2,000, basic but clean
    - Homestays/Transient Houses: ₱1,000-1,500, near UPLB or Calamba
    
    MID-RANGE RESORTS (₱2,500-5,000/night):
    - Caliraya Springs (Lumban): Lake view, ₱3,000-4,000, water activities
    - Punta Isla Lake Resort (Victoria): ₱2,500-3,500, swimming pools
    - Nuvali Area Hotels (Sta. Rosa): ₱3,000-5,000, modern, near mall
    - Los Baños Public Hot Spring Resorts: ₱2,000-4,000, spring water pools
    
    LUXURY/PREMIUM (₱5,000+/night):
    - Hidden Valley Springs (Calauan): ₱8,000-15,000, all-inclusive day tour or overnight
    - Villa Escudero (Tiaong): ₱6,000-10,000, unique waterfall restaurant experience
    - Mountain Lake Resort (Caliraya): ₱5,000-8,000, scenic lake view
    - Enchanted Kingdom Hotel: Near theme park, ₱4,000-7,000
    
    WHAT'S TYPICALLY INCLUDED:
    - Budget: Room only, shared pool access, basic amenities
    - Mid-range: Breakfast, pool access, towels, toiletries, WiFi
    - Luxury: All meals, activities, spa, guided tours, premium amenities
    
    BOOKING TIPS:
    - Book directly via phone/Facebook for better rates (no booking fee)
    - Weekday rates 20-30% cheaper than weekends
    - Peak season (Dec-Jan, Holy Week): Book 2-3 weeks ahead
    - Low season (June-Sept): Better deals, can walk-in
    - Group packages: 10-15 people get discounts
    
    CONTACT METHODS:
    - Most resorts have Facebook pages (search "[Resort Name] Laguna")
    - Call ahead: 0917/0918/0998 numbers common
    - Some listed on Booking.com, Agoda but pricier

    TOWNS & KEY PLACES:
    - Calamba: Rizal Shrine, hot springs resorts, SM Calamba, Jose Rizal's birthplace
    - Los Baños/UPLB: University campus, Mt. Makiling, hot springs, museums, Jamboree Lake
    - Sta. Rosa: Enchanted Kingdom, Nuvali, Paseo de Sta Rosa, shopping centers
    - San Pablo: Seven Lakes, buko pie capital, coconut-based delicacies
    - Pagsanjan: Pagsanjan Falls, river adventure, boat rides through rapids
    - Bay: Laguna de Bay shoreline, seafood restaurants, local festivals
    - Biñan: Historical church, commercial centers, near Sta. Rosa attractions

    ADDITIONAL LAGUNA TOWNS & PLACES:
    - Alaminos: Nagbalon Falls, rural scenic areas, farm resorts
    - Cabuyao: Industrial city near Sta. Rosa, Evia Lifestyle Center
    - Calauan: Hidden Valley Springs, pineapple plantations, rural charm
    - Cavinti: Cavinti Underground River, Sierra Madre views, Caliraya Lake nearby
    - Famy: Rural town, rice terraces, old Spanish church
    - Kalayaan: Small municipality, farming community, quiet rural life
    - Liliw: Tsinelas (sandal) capital, shoe shops everywhere, old churches
    - Luisiana: Near Los Baños, residential town, Sta. Maria hot springs nearby
    - Lumban: Embroidery capital, local handicrafts, Caliraya Springs resort
    - Mabitac: Rural farming town, old Spanish church, rice fields
    - Magdalena: Agricultural town, coconut plantations, buko pie
    - Majayjay: Taytay Falls, old Baroque church (UNESCO nominee), scenic mountain views
    - Nagcarlan: Underground Cemetery (historical site), Bunga Falls, old church
    - Paete: Woodcarving capital, papier-mâché industry, artists' town
    - Pagsanjan: Pagsanjan Falls, river adventure, shooting the rapids
    - Pakil: Turumba Festival, old church with miraculous statue, lakeside town
    - Pangil: Embroidery and woodcarving, scenic views, hidden falls
    - Pila: Heritage town, old Spanish houses, historical churches
    - Rizal: Near Laguna de Bay, fishing community, seafood
    - San Pedro: Border with Metro Manila, industrial and residential, near Nuvali
    - Santa Cruz: Capital of Laguna, government center, major transportation hub
    - Santa Maria: Near Los Baños, hot springs area, Mt. Makiling foothills
    - Siniloan: Gateway to Sierra Madre, Buruwisan Falls, cool climate
    - Victoria: Near San Pablo, fruit farms, Mount Banahaw view
    
    WATERFALLS & NATURE SPOTS:
    - Taytay Falls (Majayjay): Beautiful multi-tiered waterfall, swimming, ₱20 entrance
    - Cavinti Falls: Near Cavinti, accessible after Caliraya lake
    - Hulugan Falls (Luisiana): 70-foot waterfall, bamboo raft, ₱50 entrance
    - Buruwisan Falls (Siniloan): Remote waterfall, trekking required
    - Nagbalon Falls (Alaminos): Hidden gem, swimming allowed
    - Caliraya Lake: Boating, fishing, resorts, windsurfing
    - Lumot Lake: Smaller lake near Caliraya, scenic, resorts
    
    FOOD & SPECIALTIES BY TOWN:
    - San Pablo: Buko pie, native sweets, coconut-based products
    - Los Baños: Kesong puti (white cheese), hot spring eggs, street food
    - Sta. Rosa: Mall food courts, international chains
    - Pagsanjan: River fish, local kakanin (rice cakes)
    - Laguna (general): Uraro cookies, espasol, puto bumbong
    
    ACTIVITIES & EXPERIENCES:
    - Shooting the rapids: Pagsanjan Falls boat adventure
    - Shopping for sandals: Liliw town (tsinelas capital)
    - Woodcarving: Paete town, buy hand-carved items
    - Embroidery: Lumban and Pangil, traditional embroidered barong and dresses
    - Hot springs: Los Baños area, many budget to luxury resorts
    - Lake activities: Caliraya - windsurfing, fishing, boating
    - Heritage tours: Pila and Nagcarlan, old Spanish architecture
    - Mountain hiking: Mt. Makiling (Los Baños), Mt. Banahaw borders
    - Festival visits: Turumba (Pakil), various town fiestas
    
    DISTANCE REFERENCE:
    - Calamba to Los Baños/UPLB: 10-15 km, 20-30 mins
    - Calamba to Sta. Rosa: 15-20 km, 25-35 mins
    - Calamba to San Pablo: 30-35 km, 45-60 mins
    - Calamba to Pagsanjan: 35-40 km, 50-70 mins
    - Sta. Rosa to San Pedro: 5-10 km, 10-15 mins
    - Los Baños to Bay: 10-15 km, 15-20 mins
    - San Pablo to Nagcarlan: 10-12 km, 15-25 mins
    - Santa Cruz (capital) is central - 20-40 mins to most towns

    
    FOOD SPECIALTIES:
    - Buko Pie: Famous Laguna coconut pie (₱150-250), available in San Pablo and Los Baños
    - Kesong Puti: White cheese from Los Baños, often paired with hot pandesal
    - Uraro: Arrowroot cookies, traditional Laguna delicacy
    - Fresh Buko Juice: Refreshing coconut water, sold everywhere
    - Espasol: Sweet rice cakes, chewy and delicious
    
    TRANSPORT & DISTANCES:
    - Most Laguna towns are 15-45 minutes apart by car
    - From Manila to Laguna: 1.5-3 hours depending on destination
    - Main transport: Jeepneys (₱20-50), tricycles (₱20-100), UV Express (₱50-150)
    - Main routes: SLEX, Manila South Road, Calamba-Los Baños road

    USER QUERY: "{user_input}"
    USER INTENTS: {intents}

    RESPONSE RULES:
    1. If database has results → Describe them enthusiastically with specific names and locations
    2. If database is empty → Use backup knowledge confidently with specific details
    3. BANNED PHRASES - NEVER USE:
       ❌ "I'm not sure" ❌ "I don't know" ❌ "let me explore"  
       ❌ "let's explore further" ❌ "I'm having trouble"
       
       Instead use backup knowledge above or say:
       ✅ "While I don't have exact details, here's what I know..."
       ✅ "Based on typical Laguna places, here's what to expect..."
    4. Include practical details: prices, how to get there, what to bring, best time to visit
    5. Be specific: use actual place names, not generic descriptions
    6. Keep responses focused (3-5 short paragraphs or bullet lists)
    7. End with a follow-up question to continue conversation
    8. Use friendly, enthusiastic tone like a local friend giving recommendations
    9. CONVERSATION CONTINUITY:
       - If user gives short follow-up ("overnight", "yes", "how much"), refer to previous context
       - Example: User asked about relax spots, then says "overnight" → Recommend overnight resorts
       - Don't treat each message as isolated - maintain conversation flow
       - If uncertain about follow-up meaning, clarify: "Are you asking about overnight accommodation options?"

    INTENT-SPECIFIC GUIDANCE:
    - Food: Mention local specialties (buko pie, kesong puti), specific restaurants if known
    - Transport: Include jeepney/tricycle/UV Express options, approximate fares
    - Budget: Emphasize free/cheap options, public parks, affordable eateries
    - Accommodation/Overnight: Recommend resorts with room rates, contact info, what's included. Example: "For overnight stays, I recommend: 1) [Resort] - ₱2,000-3,000/night, includes..."
    - Adventure: Highlight outdoor activities, what to bring, difficulty levels
    - Distance: Provide approximate km and travel time (already handled by special function)
    
    FORMATTING:
    - Use emojis sparingly for visual appeal (🎯 📍 🚗 💡 ⏱️)
    - Use bullet points for lists
    - Bold important place names with **name**
    - Keep it conversational and easy to read
    """
    
    print("DEBUG: Calling AI...", file=sys.stderr)
    ai_response = ask_gemini(user_input, system_prompt, api_key, history)
    # print("DEBUG: AI call done.", file=sys.stderr)
    
    # Fallback if AI fails (429 or other)
    if ai_response is None or str(ai_response).startswith("Gemini Error"):
        ai_response, db_results = fallback_laguna_response(query, db_results, web_info)

    # 4. FOLLOW-UP
    follow_up = generate_follow_up_ai(query, primary_intent, api_key)

    # Convert DB results to be JSON serializable
    # print("DEBUG: Serializing results...", file=sys.stderr)
    serializable_results = []
    for row in db_results:
        new_row = {}
        for k, v in row.items():
            if hasattr(v, 'isoformat'): # datetime
                new_row[k] = v.isoformat()
            elif hasattr(v, '__float__'): # decimal
                new_row[k] = float(v)
            else:
                new_row[k] = v
        serializable_results.append(new_row)

    print(json.dumps({
        "response": ai_response,
        "results": serializable_results,
        "intents": intents,
        "follow_up": follow_up
    }, default=str))

if __name__ == "__main__":
    try:
        # CMD ARGS: script.py <query> <session_id> <history_json> <api_key> <google_key> <google_cx>
        if len(sys.argv) < 2:
            print(json.dumps({"error": "No query provided"}))
            sys.exit(1)

        user_input = sys.argv[1]
        session_id = sys.argv[2] if len(sys.argv) > 2 else "default"
        history_json = sys.argv[3] if len(sys.argv) > 3 else "[]"
        api_key_arg = sys.argv[4] if len(sys.argv) > 4 else ""
        google_key_arg = sys.argv[5] if len(sys.argv) > 5 else ""
        google_cx_arg = sys.argv[6] if len(sys.argv) > 6 else ""

        try:
            history = json.loads(history_json)
        except:
            history = []

        # 1. CLEAN INPUT
        clean_query = normalize_language(user_input)
        clean_query = apply_synonyms(clean_query)
        # clean_query = correct_spelling(clean_query, api_key_arg) # Disable spelling for speed if needed

        # 2. DETECT TOPIC
        topic = detect_topic_ai(user_input, api_key_arg, history)
        
        # FORCE Laguna if ANY tourism keyword exists
        if detect_topic_keywords(user_input) == "laguna":
            topic = "laguna_tourism"
        print(f"DEBUG: Detected topic: {topic}", file=sys.stderr)

        # 3. ROUTE
        if topic == "laguna_tourism":
            laguna_mode(user_input, clean_query, api_key_arg, session_id, history, google_key_arg, google_cx_arg)
        else:
            response = general_mode(user_input, api_key_arg, history, google_key_arg, google_cx_arg)
            print(json.dumps({
                "response": response,
                "results": [],
                "intents": ["general"]
            }))

    except Exception as e:
        # GLOBAL FALLBACK
        print(json.dumps({
            "error": str(e),
            "response": "I encountered a system error, but I'm still here! Try asking about 'Laguna resorts' or 'food'.",
            "results": []
        }))
