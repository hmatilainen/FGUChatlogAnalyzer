# Fantasy Grounds Chatlog Analyzer

A web application that analyzes Fantasy Grounds chat logs to extract valuable insights about your game sessions. Track dice rolls, combat actions, player participation, and more!

## Features

- **Chatlog Analysis**: Upload and analyze Fantasy Grounds chatlog.html files
- **Dice Roll Statistics**: Track and analyze dice roll probabilities and outcomes
- **Combat Tracking**: Monitor combat actions and their results
- **Player Analytics**: Track player participation and engagement
- **Session History**: View detailed statistics for all your gaming sessions
- **Character Profiles**: Analyze individual character performance and actions

## Requirements

- PHP 8.1 or higher
- Composer
- Docker and Docker Compose (for containerized setup)

## Installation

1. Clone the repository:
```bash
git clone [https://github.com/yourusername/fantasy-grounds-chatlog-analyzer.git](https://github.com/hmatilainen/FGUChatlogAnalyzer.git)
cd fantasy-grounds-chatlog-analyzer
```

2. Install dependencies:
```bash
composer install
```

3. Set up the environment:
```bash
cp .env.example .env
# Edit .env with your configuration
```

4. Start the Docker containers:
```bash
docker-compose up -d
```

## Usage

1. Access the application at `http://localhost:8000`

2. Log in with your credentials

3. Upload your Fantasy Grounds chatlog.html files

4. View and analyze your game sessions

## Project Structure

```
├── src/
│   ├── Controller/     # Application controllers
│   ├── Service/        # Business logic services
│   └── Security/       # Authentication and security
├── templates/          # Twig templates
├── public/            # Public assets
└── var/               # Cache and logs
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Fantasy Grounds for creating an amazing virtual tabletop platform
- Symfony for the excellent PHP framework
- All contributors who help improve this project

## Support

If you encounter any issues or have questions, please:
1. Check the [Issues](https://github.com/yourusername/fantasy-grounds-chatlog-analyzer/issues) page
2. Create a new issue if your problem isn't already listed
3. Provide as much detail as possible about your problem

## Roadmap

- [ ] Add support for multiple game systems
- [ ] Implement advanced combat analysis
- [ ] Add export functionality for statistics
- [ ] Create API endpoints for external integration
- [ ] Add support for custom dice roll patterns 
