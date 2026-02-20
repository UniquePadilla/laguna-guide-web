"""
Live Query Debugger for Laguna AI
Run this to see exactly what happens with each query step-by-step

Usage: python live_debug.py "your query here"
"""

import sys
import os
import json

sys.path.append(os.path.join(os.path.dirname(__file__), 'api'))

from ai_helper import (
    normalize_language,
    apply_synonyms,
    detect_intent,
    detect_topic_keywords,
    detect_topic_ai,
    needs_clarification,
    search_db,
    GEMINI_API_KEY
)

# Colors for output
class C:
    HEADER = '\033[95m'
    BLUE = '\033[94m'
    CYAN = '\033[96m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    RED = '\033[91m'
    END = '\033[0m'
    BOLD = '\033[1m'

def debug_query(query):
    """Debug a query step by step"""
    print(f"\n{C.HEADER}{'='*80}")
    print(f"🔍 DEBUGGING QUERY: '{query}'")
    print(f"{'='*80}{C.END}\n")
    
    # Step 1: Original Query
    print(f"{C.BOLD}Step 1: Original Query{C.END}")
    print(f"  Input: {C.CYAN}{query}{C.END}")
    
    # Step 2: Language Normalization
    print(f"\n{C.BOLD}Step 2: Language Normalization (Tagalog → English){C.END}")
    normalized = normalize_language(query)
    print(f"  Before: {query}")
    print(f"  After:  {C.GREEN}{normalized}{C.END}")
    if normalized != query.lower():
        print(f"  {C.YELLOW}✓ Tagalog words converted{C.END}")
    else:
        print(f"  No Tagalog conversion needed")
    
    # Step 3: Synonym Replacement
    print(f"\n{C.BOLD}Step 3: Synonym Replacement{C.END}")
    with_synonyms = apply_synonyms(normalized)
    print(f"  Before: {normalized}")
    print(f"  After:  {C.GREEN}{with_synonyms}{C.END}")
    if with_synonyms != normalized:
        print(f"  {C.YELLOW}✓ Synonyms replaced{C.END}")
    else:
        print(f"  No synonyms to replace")
    
    clean_query = with_synonyms
    
    # Step 4: Intent Detection (Regex)
    print(f"\n{C.BOLD}Step 4: Intent Detection (Regex Patterns){C.END}")
    intents = detect_intent(clean_query)
    print(f"  Detected intents: {C.CYAN}{intents}{C.END}")
    if "general" in intents and len(intents) == 1:
        print(f"  {C.YELLOW}⚠ Only 'general' intent - no specific patterns matched{C.END}")
    
    # Step 5: Topic Detection (Keywords)
    print(f"\n{C.BOLD}Step 5: Topic Detection (Keyword Matching){C.END}")
    topic_keywords = detect_topic_keywords(query)
    print(f"  Result: {C.CYAN}{topic_keywords}{C.END}")
    if topic_keywords == "laguna":
        print(f"  {C.GREEN}✓ Tourism keywords found - will route to Laguna mode{C.END}")
    else:
        print(f"  {C.YELLOW}No tourism keywords - will route to General mode{C.END}")
    
    # Step 6: Topic Detection (AI)
    print(f"\n{C.BOLD}Step 6: Topic Detection (AI Classification){C.END}")
    print(f"  Calling Gemini AI to classify topic...")
    try:
        topic_ai = detect_topic_ai(query, GEMINI_API_KEY, history=[])
        print(f"  AI Result: {C.CYAN}{topic_ai}{C.END}")
        
        if topic_ai != topic_keywords:
            print(f"  {C.RED}⚠ MISMATCH! Keyword says '{topic_keywords}' but AI says '{topic_ai}'{C.END}")
        else:
            print(f"  {C.GREEN}✓ Consistent with keyword detection{C.END}")
    except Exception as e:
        print(f"  {C.RED}Error calling AI: {e}{C.END}")
        topic_ai = topic_keywords
    
    # Step 7: Clarification Check
    print(f"\n{C.BOLD}Step 7: Clarification Check{C.END}")
    needs_clarify = needs_clarification(clean_query)
    print(f"  Needs clarification: {C.CYAN}{needs_clarify}{C.END}")
    if needs_clarify:
        print(f"  {C.YELLOW}⚠ Query too vague - will ask for clarification{C.END}")
    
    # Step 8: Database Search Simulation
    print(f"\n{C.BOLD}Step 8: Database Search{C.END}")
    print(f"  Query: '{clean_query}'")
    print(f"  Intents: {intents}")
    
    # Simulate stopword removal
    stopwords = ["what", "are", "the", "where", "is", "how", "to", "go", 
                 "top", "best", "popular", "famous", "in", "laguna", "ng", 
                 "sa", "ang", "mga", "eat", "food", "find", "place", "spot", 
                 "visit", "stay", "accommodation", "hotel", "resort", "inn", 
                 "lodge", "restaurant", "cafe", "kain", "adventure", "hike", 
                 "swim", "nature", "trip", "guide", "tour", "pasyal", "falls", 
                 "mountain", "beach"]
    
    words = clean_query.split()
    keywords_before = words
    keywords_after = [w for w in words if w not in stopwords]
    
    print(f"  Keywords before stopword removal: {C.CYAN}{keywords_before}{C.END}")
    print(f"  Keywords after stopword removal:  {C.CYAN}{keywords_after if keywords_after else C.RED + '[] EMPTY!' + C.END}{C.END}")
    
    if not keywords_after:
        print(f"  {C.RED}🔴 CRITICAL BUG: All keywords removed!{C.END}")
        print(f"  {C.RED}This query will fail because there are no keywords to search for.{C.END}")
    
    # Try actual database search
    try:
        results = search_db(clean_query, intents)
        print(f"\n  Database Results: {C.GREEN}{len(results)} found{C.END}")
        if results:
            for i, r in enumerate(results[:3], 1):
                print(f"    {i}. {r['name']} ({r['category']})")
        else:
            print(f"  {C.YELLOW}⚠ No results found - will fallback to web search or AI{C.END}")
    except Exception as e:
        print(f"  {C.RED}Database search error: {e}{C.END}")
    
    # Step 9: Final Routing Decision
    print(f"\n{C.BOLD}Step 9: Final Routing Decision{C.END}")
    final_topic = "laguna_tourism" if topic_ai == "laguna_tourism" or (topic_keywords == "laguna" and topic_ai != "general") else "general"
    print(f"  Route to: {C.BOLD}{C.CYAN}{final_topic.upper()}_MODE{C.END}")
    
    # Summary
    print(f"\n{C.HEADER}{'='*80}")
    print(f"📊 SUMMARY")
    print(f"{'='*80}{C.END}")
    print(f"  Original Query:     {query}")
    print(f"  Cleaned Query:      {clean_query}")
    print(f"  Intents:            {intents}")
    print(f"  Topic (Keywords):   {topic_keywords}")
    print(f"  Topic (AI):         {topic_ai}")
    print(f"  Routing:            {final_topic}_mode")
    print(f"  Clarification:      {needs_clarify}")
    print(f"  DB Keywords:        {keywords_after if keywords_after else C.RED + 'EMPTY (BUG!)' + C.END}")
    
    # Issues found
    issues = []
    if not keywords_after:
        issues.append("🔴 All keywords removed by stopwords - query will fail")
    if topic_keywords != topic_ai and topic_ai != "general":
        issues.append(f"⚠ Topic detection mismatch: keywords={topic_keywords}, AI={topic_ai}")
    if "transport" in intents and any(i in intents for i in ['tourist_spot', 'accommodation', 'food']):
        issues.append("⚠ Transport intent will be removed during search (Bug #3)")
    if needs_clarify:
        issues.append("⚠ Query too vague - needs clarification")
    
    if issues:
        print(f"\n{C.RED}⚠ ISSUES DETECTED:{C.END}")
        for issue in issues:
            print(f"  • {issue}")
    else:
        print(f"\n{C.GREEN}✓ No obvious issues detected{C.END}")
    
    print()

def main():
    if len(sys.argv) > 1:
        # Query from command line
        query = " ".join(sys.argv[1:])
        debug_query(query)
    else:
        # Interactive mode
        print(f"{C.HEADER}{'='*80}")
        print("🔍 Laguna AI Live Debugger")
        print(f"{'='*80}{C.END}\n")
        print("Enter queries to debug (or 'quit' to exit)")
        print("Examples:")
        print("  - i love you Doquerainee")
        print("  - how km sm calamba to uplb")
        print("  - nature trip in laguna")
        print("  - cheap resorts")
        print()
        
        while True:
            try:
                query = input(f"{C.BOLD}Query: {C.END}").strip()
                if query.lower() in ['quit', 'exit', 'q']:
                    break
                if not query:
                    continue
                debug_query(query)
            except KeyboardInterrupt:
                print("\n\nBye!")
                break

if __name__ == "__main__":
    main()
