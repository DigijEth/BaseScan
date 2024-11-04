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
