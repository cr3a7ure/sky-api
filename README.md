This project is an instance of [API-Platform](https://api-platform.com), it is part of automated RESTFul API discovery.

Using [HYDRA](http://www.hydra-cg.com/) vocabulary terms to describe the service, [schema.org](https://schema.org/) terms for data model and actions, I tried to automate the API discovery.

This is a HYDRA wrapper server that receives data from [skyscanner API](https://skyscanner.github.io/slate/#browse-quotes) and [Google QPX](https://developers.google.com/qpx-express/) and annotates them with semantic terms.

Add your API keys at `/app/config/parameters.yml`.

Note that it uses a fork from [API-Platform core ](https://github.com/cr3a7ure/core/tree/docminor) in order to create JSON-LD graphs that can be easily unified with others.

More info: [API-Resolver](https://github.com/cr3a7ure/api-resolver).