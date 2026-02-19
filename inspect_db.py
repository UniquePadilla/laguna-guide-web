
import mysql.connector
import json

DB_CONFIG = {
    'user': 'if0_41199400',
    'password': 'uniqueken112',
    'host': 'sql113.infinityfree.com',
    'database': 'if0_41199400_tourist_guide_db',
    'port': 3306
}

def inspect_db():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        
        print("Checking 'spots' table schema...")
        cursor.execute("DESCRIBE spots")
        columns = [row[0] for row in cursor.fetchall()]
        print(f"Columns: {columns}")
        
        print("\nChecking sample data...")
        cursor.execute("SELECT * FROM spots LIMIT 1")
        row = cursor.fetchone()
        print(f"Sample row: {row}")
        
        conn.close()
    except Exception as e:
        print(f"Database Error: {e}")

if __name__ == "__main__":
    inspect_db()
