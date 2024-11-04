# BaseScan
Web app Security Scanner for base chain contracts.


**Overview**

We'll implement the following changes:

1. **Add User Authentication** (Recommended): Since the settings page will handle sensitive information, we'll secure it using basic authentication.

2. **Create a Settings Page**: Add a route and template for the settings page where you can input and update your API keys.

3. **Store API Keys Securely**: Use a local SQLite database to store the API keys securely.

4. **Modify the Application to Use Stored API Keys**: Update the application logic to retrieve the API keys from the database.

---

### **Step-by-Step Implementation**

#### **1. Set Up the Database**

We'll use SQLite, which is lightweight and doesn't require any setup. We'll use SQLAlchemy for ORM (Object-Relational Mapping).

**Install SQLAlchemy:**

```bash
pip install SQLAlchemy
```

**Update `requirements.txt` (if using):**

```text
Flask
requests
web3
python-dotenv
SQLAlchemy
```

#### **2. Update the Project Structure**

```
basechain_scanner/
├── app.py
├── models.py
├── config.py
├── templates/
│   ├── index.html
│   └── settings.html
└── static/
```

- **models.py**: Contains database models.
- **config.py**: Contains configuration classes.
- **templates/settings.html**: Template for the settings page.

#### **3. Configure the Application**

**Create `config.py`:**

```python
import os
basedir = os.path.abspath(os.path.dirname(__file__))

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY', 'your_secret_key_here')
    SQLALCHEMY_DATABASE_URI = 'sqlite:///' + os.path.join(basedir, 'app.db')
    SQLALCHEMY_TRACK_MODIFICATIONS = False
```

**Note:** Replace `'your_secret_key_here'` with a strong secret key.

#### **4. Define the Database Models**

**Create `models.py`:**

```python
from app import db

class APIKeys(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    basechain_rpc_url = db.Column(db.String(256))
    token_sniffer_api_key = db.Column(db.String(256))
```

#### **5. Update `app.py`**

**Modify `app.py` to include the following changes:**

```python
import os
import requests
from flask import Flask, render_template, request, redirect, url_for, flash
from web3 import Web3
from dotenv import load_dotenv
from flask_sqlalchemy import SQLAlchemy
from config import Config
from functools import wraps

load_dotenv()

app = Flask(__name__)
app.config.from_object(Config)
db = SQLAlchemy(app)

# Import models after initializing db
from models import APIKeys

# Authentication decorator
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Simple authentication check
        authenticated = request.authorization and request.authorization.username == 'admin' and request.authorization.password == 'password'
        if not authenticated:
            return app.response_class(
                status=401,
                headers={'WWW-Authenticate': 'Basic realm="Login Required"'},
            )
        return f(*args, **kwargs)
    return decorated_function

def get_api_keys():
    keys = APIKeys.query.first()
    if keys:
        return keys
    else:
        # Return default empty keys if not set
        return APIKeys(basechain_rpc_url='', token_sniffer_api_key='')

@app.route('/settings', methods=['GET', 'POST'])
@login_required
def settings():
    if request.method == 'POST':
        basechain_rpc_url = request.form['basechain_rpc_url']
        token_sniffer_api_key = request.form['token_sniffer_api_key']

        keys = APIKeys.query.first()
        if not keys:
            keys = APIKeys()
            db.session.add(keys)

        keys.basechain_rpc_url = basechain_rpc_url
        keys.token_sniffer_api_key = token_sniffer_api_key
        db.session.commit()
        flash('API keys updated successfully.', 'success')
        return redirect(url_for('index'))

    keys = get_api_keys()
    return render_template('settings.html', keys=keys)

@app.route('/')
def index():
    keys = get_api_keys()
    if not keys.basechain_rpc_url or not keys.token_sniffer_api_key:
        flash('Please set your API keys in the settings page.', 'warning')
        return redirect(url_for('settings'))

    # Connect to Basechain
    web3 = Web3(Web3.HTTPProvider(keys.basechain_rpc_url))

    tokens = get_new_tokens(web3)
    analyzed_tokens = []

    for token in tokens:
        analysis = analyze_token(token['contract_address'], keys.token_sniffer_api_key)
        analyzed_tokens.append({
            'name': token['name'],
            'symbol': token['symbol'],
            'status': analysis['status'],
            'message': analysis['message']
        })

    return render_template('index.html', tokens=analyzed_tokens)

def get_new_tokens(web3):
    latest_block = web3.eth.block_number
    tokens = []

    # Number of blocks to scan
    blocks_to_scan = 5

    for block_number in range(latest_block - blocks_to_scan + 1, latest_block + 1):
        block = web3.eth.get_block(block_number, full_transactions=True)
        for tx in block.transactions:
            # Check for contract creation transactions
            if tx['to'] is None:
                receipt = web3.eth.get_transaction_receipt(tx['hash'])
                contract_address = receipt.contractAddress
                if contract_address:
                    # Attempt to get token details
                    token_info = get_token_details(web3, contract_address)
                    if token_info:
                        tokens.append(token_info)

    return tokens

def get_token_details(web3, contract_address):
    # ERC-20 ABI for name and symbol
    abi = [
        {
            "constant": True,
            "inputs": [],
            "name": "name",
            "outputs": [{"name": "", "type": "string"}],
            "type": "function"
        },
        {
            "constant": True,
            "inputs": [],
            "name": "symbol",
            "outputs": [{"name": "", "type": "string"}],
            "type": "function"
        }
    ]

    contract = web3.eth.contract(address=contract_address, abi=abi)
    try:
        name = contract.functions.name().call()
        symbol = contract.functions.symbol().call()
        return {
            'name': name,
            'symbol': symbol,
            'contract_address': contract_address
        }
    except:
        return None

def analyze_token(contract_address, token_sniffer_api_key):
    api_key = token_sniffer_api_key
    headers = {
        'Authorization': f'Bearer {api_key}'
    }
    url = f'https://tokensniffer.com/api/tokens/{contract_address}'
    response = requests.get(url, headers=headers)

    if response.status_code != 200:
        return {'status': 'Unknown', 'message': 'Unable to fetch analysis.'}

    data = response.json()

    if data.get('is_scam'):
        return {'status': '⚠️ Possible Scam', 'message': data.get('scam_details', 'No details provided.')}
    else:
        return {'status': '✅ Safe', 'message': 'No issues detected.'}

if __name__ == '__main__':
    # Create database tables
    with app.app_context():
        db.create_all()
    app.run(debug=True)
```

**Key Changes:**

- **Database Initialization**: Set up SQLAlchemy with the application.

- **`APIKeys` Model**: Represents the table to store API keys.

- **Authentication Decorator**: `login_required` to protect the settings page.

- **Settings Route (`/settings`)**: Allows viewing and updating API keys.

- **Fetching API Keys**: The application retrieves API keys from the database using `get_api_keys()`.

- **Modified `index()` Function**: Checks if API keys are set; if not, redirects to the settings page.

#### **6. Create the Settings Template**

**Create `templates/settings.html`:**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Crypto Scanner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        form {
            max-width: 600px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
        }
        .flash {
            padding: 10px;
            background-color: #f0ad4e;
            color: #fff;
            margin-bottom: 20px;
        }
        .success {
            background-color: #5cb85c;
        }
        .warning {
            background-color: #d9534f;
        }
    </style>
</head>
<body>
    <h1>Settings</h1>
    {% with messages = get_flashed_messages(with_categories=true) %}
        {% if messages %}
            {% for category, message in messages %}
                <div class="flash {{ category }}">{{ message }}</div>
            {% endfor %}
        {% endif %}
    {% endwith %}
    <form method="post">
        <label for="basechain_rpc_url">Basechain RPC URL</label>
        <input type="text" id="basechain_rpc_url" name="basechain_rpc_url" value="{{ keys.basechain_rpc_url }}" required>

        <label for="token_sniffer_api_key">Token Sniffer API Key</label>
        <input type="text" id="token_sniffer_api_key" name="token_sniffer_api_key" value="{{ keys.token_sniffer_api_key }}" required>

        <button type="submit">Save Settings</button>
    </form>
</body>
</html>
```

#### **7. Modify `index.html` to Show Flash Messages**

**Update `templates/index.html`:**

Add the following block after the `<h1>` tag to display flash messages:

```html
{% with messages = get_flashed_messages(with_categories=true) %}
    {% if messages %}
        {% for category, message in messages %}
            <div class="flash {{ category }}">{{ message }}</div>
        {% endfor %}
    {% endif %}
{% endwith %}
```

Add the necessary styles for the flash messages:

```html
<style>
    /* Existing styles */
    .flash {
        padding: 10px;
        background-color: #f0ad4e;
        color: #fff;
        margin-bottom: 20px;
    }
    .success {
        background-color: #5cb85c;
    }
    .warning {
        background-color: #d9534f;
    }
</style>
```

#### **8. Secure the Settings Page**

The settings page is protected using basic authentication. In the `login_required` decorator, replace `'admin'` and `'password'` with your own credentials:

```python
authenticated = request.authorization and request.authorization.username == 'admin' and request.authorization.password == 'password'
```

**Important:** For production use, consider implementing a more secure authentication mechanism.

#### **9. Run the Application**

**Initialize the Database:**

Ensure the database tables are created by running:

```bash
python app.py
```

The application should automatically create the `app.db` file in your project directory.

**Access the Application:**

1. Visit `http://127.0.0.1:5000/`:

   - If API keys are not set, you'll be redirected to the settings page.

2. Visit `http://127.0.0.1:5000/settings`:

   - You'll be prompted for authentication.
   - Enter your username and password (as set in the `login_required` decorator).
   - Input your Basechain RPC URL and Token Sniffer API Key.
   - Save the settings.

3. After saving, you'll be redirected to the main page where the tokens and their statuses are displayed.

---

### **Security Considerations**

- **Authentication**: The current implementation uses basic authentication with hard-coded credentials. For a production application:

  - Implement a secure user authentication system (e.g., Flask-Login).
  - Store user credentials securely (hashed passwords).
  - Use HTTPS to encrypt data in transit.

- **API Key Storage**:

  - API keys are stored in a local SQLite database.
  - Ensure the database file (`app.db`) is secured and not accessible publicly.
  - Consider encrypting sensitive data in the database.

- **Secret Key**:

  - The `SECRET_KEY` in `config.py` is used for securely signing session data.
  - Generate a strong, random secret key for production use.

- **Error Handling**:

  - Implement proper error handling to prevent the exposure of sensitive information.
  - Use try-except blocks around critical operations.

---

### **Additional Enhancements**

#### **1. Improve the User Interface**

- **Use a CSS Framework**: Incorporate Bootstrap or another CSS framework to enhance the look and feel of the application.

- **Add Navigation Links**: Include links to navigate between the main page and the settings page.

#### **2. Implement Better Authentication**

- **Flask-Login**:

  - Install Flask-Login:

    ```bash
    pip install Flask-Login
    ```

  - Implement user registration and login functionality.

#### **3. Enhance Security**

- **Use Environment Variables for Secret Key**:

  - Set the `SECRET_KEY` using an environment variable rather than hard-coding it.

- **Encrypt API Keys in Database**:

  - Use a library like `cryptography` to encrypt API keys before storing them.

---

### **Final Project Structure**

```
basechain_scanner/
├── app.py
├── models.py
├── config.py
├── app.db
├── templates/
│   ├── index.html
│   └── settings.html
└── static/
```

---

### **Summary**

- **Settings Page**: Added a settings page accessible at `/settings`, protected by authentication, where you can input and update your API keys.

- **Database Storage**: API keys are stored securely in a SQLite database using SQLAlchemy ORM.

- **Application Logic**: Updated to retrieve API keys from the database and use them in the application.

- **Security**: Basic authentication implemented for the settings page. For production, it's recommended to enhance authentication and secure data storage.

---

### **Next Steps**

- **Secure Authentication**: Implement a robust authentication system for better security.

- **Error Handling**: Enhance error handling to cover edge cases and exceptions.

- **Logging**: Implement logging to monitor the application's behavior.

- **Testing**: Write unit tests to ensure the application's components function correctly.

- **Deployment**: When deploying to production, ensure the application runs behind a secure web server (e.g., Nginx) and use WSGI servers like Gunicorn or uWSGI.

---

### **Support**

If you have any questions or need further assistance with the implementation, feel free to ask!
