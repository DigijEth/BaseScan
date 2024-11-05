(async () => {
    // Basechain RPC URL
    const basechainRpcUrl = 'https://mainnet.base.org'; // Replace with actual Basechain RPC URL

    // Token Sniffer API Key
    const tokenSnifferApiKey = 'YOUR_TOKEN_SNIFFER_API_KEY'; // Replace with your Token Sniffer API key

    // Initialize provider
    const provider = new ethers.providers.JsonRpcProvider(basechainRpcUrl);

    // ABI for ERC-20 name and symbol
    const erc20Abi = [
        "function name() view returns (string)",
        "function symbol() view returns (string)"
    ];

    // Get recent blocks
    const latestBlockNumber = await provider.getBlockNumber();
    const blocksToScan = 5; // Number of recent blocks to scan

    let tokens = [];

    document.getElementById('loading').innerText = 'Scanning blocks...';

    for (let i = 0; i < blocksToScan; i++) {
        const blockNumber = latestBlockNumber - i;
        const block = await provider.getBlockWithTransactions(blockNumber);

        for (const tx of block.transactions) {
            if (!tx.to) {
                // Contract creation transaction
                const receipt = await provider.getTransactionReceipt(tx.hash);
                const contractAddress = receipt.contractAddress;

                if (contractAddress) {
                    // Try to instantiate contract as ERC-20
                    const contract = new ethers.Contract(contractAddress, erc20Abi, provider);

                    try {
                        const name = await contract.name();
                        const symbol = await contract.symbol();

                        const token = {
                            name: name,
                            symbol: symbol,
                            contractAddress: contractAddress
                        };

                        tokens.push(token);

                    } catch (err) {
                        // Not an ERC-20 token
                    }
                }
            }
        }
    }

    // Now analyze tokens using Token Sniffer API

    const tokenListElement = document.getElementById('token-list');
    tokenListElement.innerHTML = ''; // Clear loading message

    for (const token of tokens) {
        // Analyze token
        const analysis = await analyzeToken(token.contractAddress, tokenSnifferApiKey);
        token.status = analysis.status;
        token.message = analysis.message;

        // Create UI elements
        const tokenItem = document.createElement('div');
        tokenItem.className = 'token-item';
        tokenItem.dataset.contractAddress = token.contractAddress;
        tokenItem.dataset.name = token.name;
        tokenItem.dataset.symbol = token.symbol;
        tokenItem.dataset.status = token.status;
        tokenItem.dataset.message = token.message;

        const statusIcon = document.createElement('span');
        statusIcon.className = 'status-icon';

        if (token.status === 'Safe') {
            statusIcon.innerHTML = '&#10004;'; // ✓
            statusIcon.classList.add('green-checkmark');
        } else if (token.status === 'Scam') {
            statusIcon.innerHTML = '&#10060;'; // ✘
            statusIcon.classList.add('red-x');
        } else {
            statusIcon.innerHTML = '&#10067;'; // ❓
            statusIcon.classList.add('yellow-question');
        }

        const tokenName = document.createElement('span');
        tokenName.className = 'token-name';
        tokenName.innerText = `${token.symbol} (${token.name})`;

        tokenItem.appendChild(statusIcon);
        tokenItem.appendChild(tokenName);

        tokenItem.addEventListener('click', (event) => {
            displayTokenInfo(token);
        });

        tokenListElement.appendChild(tokenItem);
    }

    if (tokens.length === 0) {
        tokenListElement.innerHTML = '<div>No new tokens found.</div>';
    }

    // Function to analyze token using Token Sniffer API
    async function analyzeToken(contractAddress, apiKey) {
        const url = `https://tokensniffer.com/api/tokens/${contractAddress}`;
        try {
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${apiKey}`
                }
            });

            if (response.status !== 200) {
                return { status: 'Unknown', message: 'Unable to fetch analysis.' };
            }

            const data = await response.json();

            if (data.is_scam) {
                return { status: 'Scam', message: data.scam_details || 'No details provided.' };
            } else if (data.is_risky) {
                return { status: 'Risky', message: data.risk_details || 'No details provided.' };
            } else {
                return { status: 'Safe', message: 'No issues detected.' };
            }
        } catch (error) {
            console.error(error);
            return { status: 'Unknown', message: 'Error during analysis.' };
        }
    }

    // Function to display token info
    function displayTokenInfo(token) {
        const tokenInfoElement = document.getElementById('token-info');
        tokenInfoElement.innerHTML = `
            <h2>${token.symbol} (${token.name})</h2>
            <p><strong>Status:</strong> ${token.status}</p>
            <p><strong>Message:</strong> ${token.message}</p>
            <p><strong>Contract Address:</strong> ${token.contractAddress}</p>
        `;
    }
})();
