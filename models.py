from app import db

class APIKeys(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    basechain_rpc_url = db.Column(db.String(256))
    token_sniffer_api_key = db.Column(db.String(256))
