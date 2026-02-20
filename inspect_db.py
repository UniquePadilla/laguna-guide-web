
import mysql.connector
import json

DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': '127.0.0.1',
    'database': 'tourist_guide_db'
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
