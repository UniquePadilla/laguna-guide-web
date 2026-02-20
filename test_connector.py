import sys
import os
sys.path.append(os.path.join(os.path.dirname(__file__), 'api'))
from ai_helper import get_db_connection

def test_connector_behavior():
    print("\n--- Testing Connector Behavior with %%s ---")
    conn = get_db_connection()
    if not conn:
        print("Could not connect to DB")
        return

    cursor = conn.cursor()
    
    # Case 5: Mix %s and %%bus%%
    sql = "SELECT %s FROM DUAL WHERE 'abc' LIKE '%%bus%%'"
    print(f"Executing: {sql}")
    try:
        cursor.execute(sql, ['1'])
        cursor.fetchall()
        print("Success: Mix %s and %%bus%%")
    except Exception as e:
        print(f"Failed: Mix %s and %%bus%% -> {e}")

    # Case 4 again: Mix %s and %%shrine%%
    sql = "SELECT %s FROM DUAL WHERE 'abc' LIKE '%%shrine%%'"
    print(f"Executing: {sql}")
    try:
        cursor.execute(sql, ['1'])
        cursor.fetchall()
        print("Success: Mix %s and %%shrine%%")
    except Exception as e:
        print(f"Failed: Mix %s and %%shrine%% -> {e}")

    conn.close()

if __name__ == "__main__":
    test_connector_behavior()
