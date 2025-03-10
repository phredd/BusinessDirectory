const path = require('path');

module.exports =
{
	entry: './src/js/main.js',
	output:
	{
		filename: 'bundle.js',
		path: path.resolve(__dirname, 'public/assets/js'),
	},
	module:
	{
		rules:
		[
			{
				test: /\.css$/,
				use: ['style-loader', 'css-loader'],
			},
		],
	}
};
