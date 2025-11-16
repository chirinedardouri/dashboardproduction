pipeline {
    agent any

    tools {
        maven 'M2_HOME'
    }

    stages {
        stage('Hello World') {
            steps {
                echo 'Hello world'
            }
        }

        stage('GIT') {
            steps {
                git branch: 'main', 
                url: 'https://github.com/chirinedardouri/dashboardproduction.git'
            }
        }

        stage('Maven') {
            steps {
                sh "mvn -version"
            }
        }
    }

    post {
        success {
            echo 'Pipeline finished successfully!'
        }
        failure {
            echo 'Pipeline failed!'
        }
    }
}
