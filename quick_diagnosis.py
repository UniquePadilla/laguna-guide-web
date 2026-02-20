"""
Quick Diagnosis for Your Two Problematic Queries

This will show you EXACTLY what's happening with:
1. "i love you Doquerainee" 
2. "how km sm calamba to uplb"

Run: python quick_diagnosis.py
"""

import sys
import os
sys.path.append(os.path.join(os.path.dirname(__file__), 'api'))

from ai_helper import (
    normalize_language,
    apply_synonyms, 
    detect_intent,
    detect_topic_keywords,
    detect_topic_ai,
    GEMINI_API_KEY
)

print("\n" + "="*80)
print("🔍 DIAGNOSING YOUR TWO PROBLEMATIC QUERIES")
print("="*80 + "\n")

# Query 1: "i love you Doquerainee"
print("━"*80)
print("QUERY 1: 'i love you Doquerainee'")
print("━"*80)
print("\nEXPECTED: Should go to GENERAL mode (not tourism)")
print("ACTUAL: Going to Laguna mode and talking about weather\n")

query1 = "i love you Doquerainee"

print("Step-by-step breakdown:")
print(f"  1. Original: {query1}")

clean1 = apply_synonyms(normalize_language(query1))
print(f"  2. Cleaned:  {clean1}")

topic_kw1 = detect_topic_keywords(query1)
print(f"  3. Topic (keywords): {topic_kw1}")

print(f"  4. Topic (AI): Calling Gemini...")
try:
    topic_ai1 = detect_topic_ai(query1, GEMINI_API_KEY, [])
    print(f"     Result: {topic_ai1}")
except Exception as e:
    print(f"     Error: {e}")
    topic_ai1 = topic_kw1

intents1 = detect_intent(clean1)
print(f"  5. Intents: {intents1}")

print(f"\n🔍 DIAGNOSIS:")
if topic_kw1 == "general" and topic_ai1 == "laguna_tourism":
    print(f"  ❌ BUG FOUND!")
    print(f"     • Keyword detection says: '{topic_kw1}' ✓ (correct)")
    print(f"     • AI detection says: '{topic_ai1}' ✗ (wrong!)")
    print(f"     • AI is incorrectly classifying emotional/social queries as tourism")
    print(f"\n  💡 FIX: Update the AI prompt in detect_topic_ai() (line 324)")
    print(f"     Change: 'If ambiguous, default to laguna_tourism'")
    print(f"     To: 'If clearly not tourism (like greetings, emotions), select general'")
elif topic_kw1 == "laguna" or topic_ai1 == "laguna_tourism":
    print(f"  ❌ PROBLEM: Query being routed to Laguna mode")
    print(f"     This query has no tourism keywords!")
    print(f"     Should route to GENERAL mode for casual conversation")
else:
    print(f"  ✓ Topic detection seems correct")

# Query 2: "how km sm calamba to uplb"
print("\n\n" + "━"*80)
print("QUERY 2: 'how km sm calamba to uplb'")
print("━"*80)
print("\nEXPECTED: Should give distance/route info between SM Calamba and UPLB")
print("ACTUAL: Says 'I'm not sure, but let's explore further!'\n")

query2 = "how km sm calamba to uplb"

print("Step-by-step breakdown:")
print(f"  1. Original: {query2}")

clean2 = apply_synonyms(normalize_language(query2))
print(f"  2. Cleaned:  {clean2}")

# Check stopwords
stopwords = ["what", "are", "the", "where", "is", "how", "to", "go", 
             "top", "best", "popular", "famous", "in", "laguna", "ng", 
             "sa", "ang", "mga", "eat", "food", "find", "place", "spot", 
             "visit", "stay", "accommodation", "hotel", "resort", "inn", 
             "lodge", "restaurant", "cafe", "kain", "adventure", "hike", 
             "swim", "nature", "trip", "guide", "tour", "pasyal", "falls", 
             "mountain", "beach"]

words2 = clean2.split()
remaining2 = [w for w in words2 if w not in stopwords]

print(f"  3. Words: {words2}")
print(f"  4. After stopwords removed: {remaining2 if remaining2 else '[] EMPTY!'}")

topic_kw2 = detect_topic_keywords(query2)
print(f"  5. Topic (keywords): {topic_kw2}")

intents2 = detect_intent(clean2)
print(f"  6. Intents: {intents2}")

print(f"\n🔍 DIAGNOSIS:")

problems = []

if not remaining2:
    problems.append("Keywords removed by stopwords")
    print(f"  ❌ CRITICAL BUG: All keywords removed by stopwords!")
    print(f"     • Original keywords: {words2}")
    print(f"     • After removal: EMPTY")
    print(f"     • Database search will fail - no keywords to search for")
    print(f"\n  💡 FIX: Remove 'to', 'how' from stopwords list (line 360)")
    print(f"     Or better: Only keep articles/prepositions as stopwords")

if "transport" not in intents2 and "distance" not in intents2:
    problems.append("Distance/transport intent not detected")
    print(f"  ❌ INTENT DETECTION ISSUE:")
    print(f"     • Query asks about DISTANCE ('how km')")
    print(f"     • Current intents: {intents2}")
    print(f"     • Missing: 'distance' or 'transport' intent")
    print(f"\n  💡 FIX: Add distance pattern to detect_intent() (line 230)")
    print(f"     Add: if re.search(r'how km|distance|how far', message): intents.append('distance')")

if topic_kw2 == "general":
    problems.append("Topic not detected as Laguna")
    print(f"  ❌ TOPIC DETECTION ISSUE:")
    print(f"     • Query mentions 'calamba' and 'uplb' (both in Laguna)")
    print(f"     • Current topic: {topic_kw2}")
    print(f"     • Should be: laguna_tourism")
    print(f"\n  💡 FIX: Add 'calamba', 'uplb', 'km' to tourism keywords (line 274)")
    print(f"     Add: 'calamba', 'los banos', 'uplb', 'km', 'distance'")

if not problems:
    print(f"  ⚠️  Routing seems correct, but response is weak")
    print(f"     • Topic: {topic_kw2} ✓")
    print(f"     • Intents: {intents2}")
    print(f"     • Keywords: {remaining2}")
    print(f"\n     Possible reasons for weak response:")
    print(f"     1. Database has no matching results")
    print(f"     2. AI generates weak response (needs better prompt)")
    print(f"     3. Query needs special 'distance' handling")

# Summary
print("\n\n" + "="*80)
print("📊 SUMMARY")
print("="*80)
print("\nQuery 1: 'i love you Doquerainee'")
print("  Issue: Being routed to Laguna mode instead of general")
print("  Fix: Update AI classification prompt to exclude social/emotional queries")

print("\nQuery 2: 'how km sm calamba to uplb'")
if not remaining2:
    print("  Issue #1: ALL keywords removed by stopwords → Empty search")
    print("  Fix #1: Keep content words in stopwords list")
if "distance" not in intents2:
    print("  Issue #2: Distance intent not detected")
    print("  Fix #2: Add 'distance' pattern to detect_intent()")
if topic_kw2 == "general":
    print("  Issue #3: Not detected as Laguna query")
    print("  Fix #3: Add location names to tourism keywords")

print("\n" + "="*80)
print("📝 QUICK FIXES TO APPLY")
print("="*80)

print("""
1. Edit ai_helper.py line 360 - Fix stopwords:
   Remove: "how", "to", "go", "km"
   
2. Edit ai_helper.py line 274 - Add location keywords:
   Add to tourism_keywords: "calamba", "los banos", "uplb", "km", "distance"
   
3. Edit ai_helper.py line 230 - Add distance intent:
   Add: if re.search(r"how km|distance|how far|ilang km", message): intents.append("distance")
   
4. Edit ai_helper.py line 324 - Fix AI prompt:
   Change the ambiguous query handling to exclude emotional/social queries

Run 'python live_debug.py' to test your fixes interactively!
""")

print("="*80 + "\n")
