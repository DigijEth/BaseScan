**BaseScam Scanner Web Application**

A web application that scans the Basechain (a Layer 2 network on Ethereum) for new token contracts and analyzes them using Token Sniffer's API. The application displays a list of new tokens along with a risk assessment indicator (e.g., green checkmark or warning symbol).

---

## **Table of Contents**

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Project Structure](#project-structure)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [Usage](#usage)
- [Security Considerations](#security-considerations)
- [Next Steps](#next-steps)
- [License](#license)

---

## **Features**

- Scans recent blocks on the Basechain network for new token contracts.
- Fetches token details such as name and symbol.
- Analyzes token contracts using Token Sniffer's API.
- Displays the analysis results on a web page.
- Includes a secure settings page to input and update API keys.
- Basic authentication for the settings page.

---

## **Prerequisites**

- **Python 3.6+** installed on your machine.
- **pip** package installer.
- API keys for:
  - **Token Sniffer API**: Obtain from [Token Sniffer](https://tokensniffer.com/).
  - **Basechain RPC URL**: Use the official Basechain RPC endpoint or a public RPC provider.
- Basic knowledge of command-line operations.

---

## **Installation**

### **1. Clone the Repository**

```bash
git clone https://github.com/your-username/crypto-scanner-app.git
cd crypto-scanner-app
```

### **2. Set Up a Virtual Environment**

It's recommended to use a virtual environment to manage dependencies.

```bash
python -m venv venv
source venv/bin/activate  # For Windows: venv\Scripts\activate
```

### **3. Install Dependencies**

```bash
pip install -r requirements.txt
```

---

## **Project Structure**

```
crypto-scanner-app/
├── app.py
├── config.py
├── models.py
├── requirements.txt
├── templates/
│   ├── index.html
│   └── settings.html
└── static/
```

- **app.py**: Main application file containing the Flask app.
- **config.py**: Configuration settings for the application.
- **models.py**: Database models defined using SQLAlchemy.
- **requirements.txt**: List of Python dependencies.
- **templates/**: HTML templates for rendering web pages.

---

## **Configuration**

### **1. Configure Secret Key**

In `config.py`, ensure you have a strong secret key for session management.

```python
import os

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY', 'your_secret_key_here')
    SQLALCHEMY_DATABASE_URI = 'sqlite:///app.db'
    SQLALCHEMY_TRACK_MODIFICATIONS = False
```

- Replace `'your_secret_key_here'` with a strong, random string.
- Alternatively, set an environment variable `SECRET_KEY` with your secret key.

### **2. Authentication Credentials**

In `app.py`, the settings page is protected by basic authentication. Update the credentials as desired.

```python
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        authenticated = (
            request.authorization and
            request.authorization.username == 'admin' and
            request.authorization.password == 'password'
        )
        if not authenticated:
            return app.response_class(
                status=401,
                headers={'WWW-Authenticate': 'Basic realm="Login Required"'},
            )
        return f(*args, **kwargs)
    return decorated_function
```

- Replace `'admin'` and `'password'` with your preferred username and password.
- For enhanced security, consider implementing a more robust authentication system.

---

## **Running the Application**

### **1. Initialize the Database**

Before running the application, initialize the SQLite database.

```bash
python app.py
```

- The application will automatically create `app.db` in your project directory.
- The database will contain a table for storing API keys.

### **2. Run the Flask Application**

```bash
python app.py
```

- By default, the application runs in debug mode on `http://127.0.0.1:5000/`.

---

## **Usage**

### **1. Access the Application**

Open your web browser and navigate to `http://127.0.0.1:5000/`.

- If API keys are not set, you'll be redirected to the settings page.

### **2. Set Up API Keys**

Navigate to the settings page at `http://127.0.0.1:5000/settings`.

- You'll be prompted for authentication.
  - **Username**: As set in `app.py`.
  - **Password**: As set in `app.py`.

#### **Settings Page**

- **Basechain RPC URL**: Input the RPC endpoint for the Basechain network.
  - Example: `https://mainnet.base.org`
- **Token Sniffer API Key**: Input your Token Sniffer API key.

Click **"Save Settings"** to update the API keys.

### **3. View the Scanner Results**

After saving the settings, you'll be redirected to the main page.

- The application will scan recent blocks for new tokens.
- Analysis results are displayed in a table with the following columns:
  - **Token Name**
  - **Symbol**
  - **Status**: Displays "✅ Safe" or "⚠️ Possible Scam".
  - **Details**: Provides additional information from Token Sniffer.

---

## **Code Overview**

### **1. app.py**

This is the main application file that initializes the Flask app, handles routes, and contains the core logic.

Key components:

- **Imports**: Necessary libraries and modules.
- **App Configuration**: Loads configurations from `config.py`.
- **Database Initialization**: Sets up SQLAlchemy.
- **Authentication Decorator**: Protects the settings page.
- **Routes**:
  - `/`: Main page displaying the scanner results.
  - `/settings`: Settings page for API keys.
- **Functions**:
  - `get_api_keys()`: Retrieves API keys from the database.
  - `get_new_tokens(web3)`: Scans recent blocks for new tokens.
  - `get_token_details(web3, contract_address)`: Fetches token name and symbol.
  - `analyze_token(contract_address, token_sniffer_api_key)`: Analyzes the token using Token Sniffer's API.

### **2. config.py**

Contains the configuration settings for the Flask app, including:

- **SECRET_KEY**: Used for session management.
- **SQLALCHEMY_DATABASE_URI**: Database connection string.
- **SQLALCHEMY_TRACK_MODIFICATIONS**: Disables tracking modifications (recommended).

### **3. models.py**

Defines the database model using SQLAlchemy.

- **APIKeys**: Model with fields to store the Basechain RPC URL and Token Sniffer API key.

### **4. templates/index.html**

HTML template for the main page that displays the scanner results.

- Uses Jinja2 templating to iterate over tokens and display their details.
- Includes basic CSS styles for the table and flash messages.

### **5. templates/settings.html**

HTML template for the settings page.

- Provides a form to input and update the Basechain RPC URL and Token Sniffer API key.
- Protected by authentication.

---

## **Security Considerations**

- **API Keys**:
  - Stored securely in a local SQLite database.
  - Ensure the `app.db` file is not publicly accessible.
- **Authentication**:
  - Basic authentication is implemented for the settings page.
  - For production, consider using a more secure authentication method (e.g., OAuth, Flask-Login).
- **Secret Key**:
  - Generate a strong `SECRET_KEY` for session security.
  - Do not expose the secret key in public repositories.
- **HTTPS**:
  - Use HTTPS in production to encrypt data in transit.
- **Error Handling**:
  - The application includes basic error handling.
  - Enhance error handling to prevent information leakage in production.

---

## **Next Steps**

- **Enhance Authentication**:
  - Implement a robust user authentication system.
  - Use hashed passwords and secure session management.
- **UI Improvements**:
  - Integrate a CSS framework like Bootstrap for better styling.
  - Add navigation menus and improve the layout.
- **Performance Optimization**:
  - Implement caching mechanisms to reduce API calls.
  - Use background tasks for blockchain scanning.
- **Logging and Monitoring**:
  - Add logging to monitor application behavior.
  - Set up alerts for errors and exceptions.
- **Deployment**:
  - Deploy the application using a production-ready web server (e.g., Gunicorn with Nginx).
  - Configure the application for scalability and high availability.

---

## **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## **Contact**

For questions or support, please open an issue on the GitHub repository or contact the maintainer.

---

# **Detailed Walkthrough**

Below is a detailed explanation of each file and the code within.

---

## **1. app.py**

```python
import os
import requests
from flask import Flask, render_template, request, redirect, url_for, flash
from web3 import Web3
from flask_sqlalchemy import SQLAlchemy
from config import Config
from functools import wraps

app = Flask(__name__)
app.config.from_object(Config)
db = SQLAlchemy(app)

# Import models after initializing db
from models import APIKeys

# Authentication decorator
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        authenticated = (
            request.authorization and
            request.authorization.username == 'admin' and
            request.authorization.password == 'password'
        )
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

### **Key Points:**

- **Database Initialization**: The application initializes the database and creates tables if they don't exist.
- **Authentication**: The `login_required` decorator secures the settings page.
- **Routes**:
  - `/settings`: For managing API keys.
  - `/`: Main page displaying the scanner results.
- **Functions**:
  - `get_new_tokens(web3)`: Scans the recent blocks for new token contracts.
  - `get_token_details(web3, contract_address)`: Retrieves the token's name and symbol using the ERC-20 standard functions.
  - `analyze_token(contract_address, token_sniffer_api_key)`: Uses Token Sniffer's API to analyze the token.

---

## **2. config.py**

```python
import os

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY', 'your_secret_key_here')
    SQLALCHEMY_DATABASE_URI = 'sqlite:///app.db'
    SQLALCHEMY_TRACK_MODIFICATIONS = False
```

- **SECRET_KEY**: Used for securely signing the session cookie.
- **SQLALCHEMY_DATABASE_URI**: Points to the SQLite database file.
- **SQLALCHEMY_TRACK_MODIFICATIONS**: Disables the event system to save resources.

---

## **3. models.py**

```python
from app import db

class APIKeys(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    basechain_rpc_url = db.Column(db.String(256))
    token_sniffer_api_key = db.Column(db.String(256))
```

- Defines the `APIKeys` model with fields to store the RPC URL and Token Sniffer API key.

---

## **4. templates/index.html**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Crypto Scanner Results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            color: #333;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
            text-align: left;
        }
        .safe {
            color: green;
            font-weight: bold;
        }
        .scam {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Crypto Scanner Results</h1>
    {% with messages = get_flashed_messages(with_categories=true) %}
        {% if messages %}
            {% for category, message in messages %}
                <div class="flash {{ category }}">{{ message }}</div>
            {% endfor %}
        {% endif %}
    {% endwith %}
    {% if tokens %}
    <table>
        <thead>
            <tr>
                <th>Token Name</th>
                <th>Symbol</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            {% for token in tokens %}
            <tr>
                <td>{{ token.name }}</td>
                <td>{{ token.symbol }}</td>
                <td class="{% if 'Safe' in token.status %}safe{% else %}scam{% endif %}">{{ token.status }}</td>
                <td>{{ token.message }}</td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
    {% else %}
    <p>No new tokens found.</p>
    {% endif %}
</body>
</html>
```

### **Key Points:**

- Displays the scanner results in a table format.
- Uses conditional styling to highlight safe tokens and potential scams.
- Includes flash messages for user feedback.

---

## **5. templates/settings.html**

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
        h1 {
            color: #333;
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
        form {
            max-width: 600px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
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

### **Key Points:**

- Provides a form to input and update API keys.
- Displays flash messages for success or warnings.
- Protected by the authentication decorator in `app.py`.

---

## **6. requirements.txt**

```text
Flask
requests
web3
Flask_SQLAlchemy
```

- Lists all the Python packages required to run the application.
- Install using `pip install -r requirements.txt`.

---

## **7. Additional Notes**

- **Error Handling**: The application includes basic error handling. For production use, consider adding more comprehensive error handling and logging.
- **Testing**: Before deploying, test the application thoroughly to ensure all components work as expected.

---

**Enjoy your Crypto Scanner Web Application!**

Feel free to contribute to the project or customize it to suit your needs. If you have any questions or need assistance, don't hesitate to reach out.
